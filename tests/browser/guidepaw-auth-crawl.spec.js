const { test, expect } = require('@playwright/test');

const BASE_URL = process.env.GUIDEPAW_TEST_BASE_URL || 'https://10.147.18.184';
const USERNAME = process.env.GUIDEPAW_TEST_USERNAME || '';
const PASSWORD = process.env.GUIDEPAW_TEST_PASSWORD || '';

const MAX_LINKS = Number(process.env.GUIDEPAW_CRAWL_MAX_LINKS || 60);

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
    lower.startsWith('mailto:') ||
    lower.startsWith('tel:') ||
    lower.startsWith('javascript:')
  );
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

    const bodyText = await page.locator('body').innerText().catch(() => '');
    if (/Fatal error|Parse error|Warning:|Application error|SQLSTATE|Undefined function/i.test(bodyText)) {
      broken.push({ url, status, detail: 'PHP/application error text found on page' });
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
