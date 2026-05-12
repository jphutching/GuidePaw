const { test, expect } = require('@playwright/test');

const BASE_URL = process.env.GUIDEPAW_TEST_BASE_URL || 'https://10.147.18.184';
const USERNAME = process.env.GUIDEPAW_TEST_USERNAME || '';
const PASSWORD = process.env.GUIDEPAW_TEST_PASSWORD || '';
const ADMIN_USERNAME = process.env.GUIDEPAW_ADMIN_TEST_USERNAME || '';
const ADMIN_PASSWORD = process.env.GUIDEPAW_ADMIN_TEST_PASSWORD || '';

const MAX_LINKS = Number(process.env.GUIDEPAW_CRAWL_MAX_LINKS || 60);

test.setTimeout(120000);

function shouldSkip(url) {
  const lower = url.toLowerCase();

  return (
    lower.includes('logout.php') ||
    lower.includes('delete') ||
    lower.includes('remove') ||
    lower.includes('archive') ||
    lower.includes('export_backup.php') ||
    lower.includes('import_backup.php') ||
    lower.includes('training_history_export.php') ||
    lower.includes('_export.php') ||
    lower.includes('reset_password.php') ||
    lower.includes('beta_qa_checklist.php') ||
    lower.startsWith('mailto:') ||
    lower.startsWith('tel:') ||
    lower.startsWith('javascript:')
  );
}

async function visiblePageTextForErrorScan(page) {
  return page.locator('body').evaluate(body => {
    const clone = body.cloneNode(true);
    clone.querySelectorAll('.card').forEach(node => {
      if (node.querySelector('.details')) node.remove();
    });
    return clone.innerText || '';
  }).catch(() => '');
}

async function loginAsAdmin(page) {
  test.skip(!ADMIN_USERNAME || !ADMIN_PASSWORD, 'Set GUIDEPAW_ADMIN_TEST_USERNAME and GUIDEPAW_ADMIN_TEST_PASSWORD');

  await page.context().clearCookies().catch(() => {});
  await page.goto(`${BASE_URL}/logout.php`).catch(() => {});
  await page.waitForLoadState('networkidle').catch(() => {});
  await page.goto(`${BASE_URL}/login.php`);
  await expect(page.locator('input[name="username"]')).toBeVisible();

  await page.fill('input[name="username"]', ADMIN_USERNAME);
  await page.fill('input[name="password"]', ADMIN_PASSWORD);
  await page.getByRole('button', { name: /login/i }).click();

  await page.waitForLoadState('networkidle').catch(() => {});
  await expect(page).toHaveURL(/admin\.php/);
}

test('GuidePaw login works', async ({ page }) => {
  test.skip(!USERNAME || !PASSWORD, 'Set GUIDEPAW_TEST_USERNAME and GUIDEPAW_TEST_PASSWORD');

  await page.goto(`${BASE_URL}/login.php`);
  await expect(page.locator('input[name="username"]')).toBeVisible();

  await page.fill('input[name="username"]', USERNAME);
  await page.fill('input[name="password"]', PASSWORD);
  await page.getByRole('button', { name: /login/i }).click();

  await page.waitForLoadState('networkidle');
  await expect(page).not.toHaveURL(/login\.php/);
});

test('GuidePaw dogs page keeps add-dog form collapsed for existing handlers', async ({ page }) => {
  test.skip(!USERNAME || !PASSWORD, 'Set GUIDEPAW_TEST_USERNAME and GUIDEPAW_TEST_PASSWORD');

  await page.goto(`${BASE_URL}/login.php`);
  await expect(page.locator('input[name="username"]')).toBeVisible();

  await page.fill('input[name="username"]', USERNAME);
  await page.fill('input[name="password"]', PASSWORD);
  await page.getByRole('button', { name: /login/i }).click();

  await page.waitForLoadState('networkidle');
  await page.goto(`${BASE_URL}/dogs.php`);
  await page.waitForLoadState('networkidle');

  const addDogPanel = page.locator('details.add-dog-card');
  await expect(addDogPanel).toBeVisible();
  await expect(addDogPanel).not.toHaveAttribute('open', /./);
  await expect(page.locator('details.add-dog-card summary')).toContainText(/add another dog|add your first dog/i);
});

test('GuidePaw authenticated link crawl', async ({ page }) => {
  test.skip(!USERNAME || !PASSWORD, 'Set GUIDEPAW_TEST_USERNAME and GUIDEPAW_TEST_PASSWORD');

  const broken = [];
  const visited = new Set();
  const queue = [`${BASE_URL}/index.php`];

  await page.goto(`${BASE_URL}/login.php`);
  await page.fill('input[name="username"]', USERNAME);
  await page.fill('input[name="password"]', PASSWORD);
  await page.getByRole('button', { name: /login/i }).click();
  await page.waitForLoadState('networkidle');

  while (queue.length && visited.size < MAX_LINKS) {
    const url = queue.shift();
    if (!url || visited.has(url) || shouldSkip(url)) continue;

    visited.add(url);

    const response = await page.goto(url, { waitUntil: 'domcontentloaded' }).catch(error => {
      broken.push({ url, status: 'NAV_ERROR', detail: error.message });
      return null;
    });

    if (!response) continue;

    const status = response.status();
    const finalUrl = page.url();

    if (status >= 400) {
      broken.push({ url, status, detail: finalUrl });
      continue;
    }

    const bodyText = await visiblePageTextForErrorScan(page);
    const errorMatch = bodyText.match(/.{0,120}(Fatal error|Parse error|Warning:|Application error|SQLSTATE|Undefined function).{0,240}/is);
    if (errorMatch) {
      broken.push({
        url,
        status,
        detail: `PHP/application error text found on page: ${errorMatch[0].replace(/\\s+/g, ' ').trim()}`
      });
    }

    const links = await page.locator('a[href]').evaluateAll(anchors =>
      anchors.map(a => a.href).filter(Boolean)
    );

    for (const link of links) {
      if (!link.startsWith(BASE_URL)) continue;
      if (shouldSkip(link)) continue;
      const clean = link.split('#')[0];
      if (!visited.has(clean) && !queue.includes(clean)) {
        queue.push(clean);
      }
    }
  }

  console.log(`Visited ${visited.size} pages`);
  if (broken.length) {
    console.table(broken);
  }

  expect(broken).toEqual([]);
});

test('GuidePaw forum thread create and reply work for handlers', async ({ page }) => {
  test.skip(!USERNAME || !PASSWORD, 'Set GUIDEPAW_TEST_USERNAME and GUIDEPAW_TEST_PASSWORD');

  const threadTitle = `QA Forum Thread ${Date.now()}`;
  const threadBody = 'Playwright forum smoke thread.';
  const replyBody = 'Playwright forum smoke reply.';

  await page.goto(`${BASE_URL}/login.php`);
  await page.fill('input[name="username"]', USERNAME);
  await page.fill('input[name="password"]', PASSWORD);
  await page.getByRole('button', { name: /login/i }).click();
  await page.waitForLoadState('networkidle');
  await expect(page).not.toHaveURL(/login\.php/);

  await page.goto(`${BASE_URL}/forum.php`);
  await page.waitForLoadState('networkidle');
  await expect(page.getByRole('heading', { name: /forum/i })).toBeVisible();
  await expect(page.locator('form').filter({ hasText: /post thread/i })).toBeVisible();

  await page.selectOption('select[name="category"]', 'general');
  await page.fill('input[name="title"]', threadTitle);
  await page.fill('textarea[name="body"]', threadBody);
  await page.getByRole('button', { name: /post thread/i }).click();
  await page.waitForURL(/forum\.php\?thread_id=\d+/);
  await page.waitForLoadState('networkidle');

  await expect(page.getByRole('heading', { name: threadTitle })).toBeVisible();
  await expect(page.locator('body')).toContainText(threadBody);

  const replyBox = page.locator('textarea[name="reply_body"]');
  await expect(replyBox).toBeVisible();
  await replyBox.fill(replyBody);
  await page.getByRole('button', { name: /post reply/i }).click();
  await page.waitForLoadState('networkidle');

  await expect(page.locator('body')).toContainText(replyBody);
});

test('GuidePaw found-dog public report submits and reaches admin queue', async ({ page }) => {
  test.skip(!USERNAME || !PASSWORD || !ADMIN_USERNAME || !ADMIN_PASSWORD, 'Set regular and admin smoke credentials');

  const reportLocation = `QA Found Location ${Date.now()}`;
  const reportMessage = 'Automated Playwright found-dog smoke test.';

  await page.goto(`${BASE_URL}/login.php`);
  await page.fill('input[name="username"]', USERNAME);
  await page.fill('input[name="password"]', PASSWORD);
  await page.getByRole('button', { name: /login/i }).click();
  await page.waitForLoadState('networkidle');
  await expect(page).not.toHaveURL(/login\.php/);

  await page.goto(`${BASE_URL}/dogs.php`);
  await page.waitForLoadState('networkidle').catch(() => {});

  const dogProfileLink = page.locator('a[href*="dog_profile.php?dog_id="]').first();
  await expect(dogProfileLink).toBeVisible();

  const dogProfileHref = await dogProfileLink.getAttribute('href');
  expect(dogProfileHref).toBeTruthy();

  await page.goto(new URL(dogProfileHref, BASE_URL).toString());
  await page.waitForLoadState('networkidle').catch(() => {});

  const publicProfileLink = page.locator('a[href*="public_dog_profile.php?dog="]').first();
  await expect(publicProfileLink).toBeVisible();

  const publicProfileHref = await publicProfileLink.getAttribute('href');
  expect(publicProfileHref).toBeTruthy();

  await page.goto(new URL(publicProfileHref, BASE_URL).toString());
  await page.waitForLoadState('networkidle').catch(() => {});

  const reportLink = page.getByRole('link', { name: /share found location/i });
  await expect(reportLink).toBeVisible();
  await reportLink.click();
  await page.waitForLoadState('networkidle').catch(() => {});

  await page.context().grantPermissions(['geolocation'], { origin: BASE_URL }).catch(() => {});
  await page.context().setGeolocation({
    latitude: 39.7392,
    longitude: -104.9903,
    accuracy: 25,
  }).catch(() => {});

  await page.fill('input[name="finder_location"]', reportLocation);
  await page.getByRole('button', { name: /share my current location once/i }).click();
  await expect(page.locator('input[name="finder_latitude"]')).toHaveValue('39.7392000');
  await expect(page.locator('input[name="finder_longitude"]')).toHaveValue('-104.9903000');
  await expect(page.locator('input[name="finder_accuracy_m"]')).toHaveValue('25');
  await page.fill('input[name="finder_phone"]', '555-0100');
  await page.fill('textarea[name="finder_message"]', reportMessage);
  await page.getByRole('button', { name: /send location report/i }).click();
  await page.waitForLoadState('networkidle').catch(() => {});

  const submittedBody = await page.locator('body').innerText().catch(() => '');
  expect(submittedBody).toMatch(/Location report sent|notification has been queued/i);

  await loginAsAdmin(page);
  await page.goto(`${BASE_URL}/admin_found_dog_reports.php`);
  await page.waitForLoadState('networkidle').catch(() => {});

  const adminBody = await page.locator('body').innerText().catch(() => '');
  expect(adminBody).toContain(reportLocation);
  expect(adminBody).toContain(reportMessage);

  const adminHtml = await page.locator('body').evaluate(body => body.innerHTML).catch(() => '');
  const escapedReportLocation = reportLocation.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  const locationMatch = adminHtml.match(new RegExp(`${escapedReportLocation}[\\s\\S]{0,1200}?<a class="maplink" href="([^"]+)"`, 'i'));
  const locationHref = locationMatch ? locationMatch[1].replace(/&amp;/g, '&') : '';
  expect(locationHref).toContain('google.com/maps/search/?api=1&query=');
  expect(locationHref).toContain('39.7392');
});
