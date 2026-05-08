const { chromium } = require('playwright');

const baseURL = process.env.GUIDEPAW_TEST_BASE_URL || process.env.GUIDEPAW_BASE_URL || 'https://beta.guidepaw.app';
const normalUser = process.env.GUIDEPAW_TEST_USERNAME || '';
const normalPass = process.env.GUIDEPAW_TEST_PASSWORD || '';
const adminUser = process.env.GUIDEPAW_ADMIN_TEST_USERNAME || '';
const adminPass = process.env.GUIDEPAW_ADMIN_TEST_PASSWORD || '';
const maxPages = Number(process.env.GUIDEPAW_BRAND_NAV_MAX_PAGES || 90);

function shouldSkip(rawUrl) {
  const lower = rawUrl.toLowerCase();
  return (
    lower.includes('logout.php') ||
    lower.includes('delete') ||
    lower.includes('remove') ||
    lower.includes('archive') ||
    lower.includes('export') ||
    lower.includes('/uploads/') ||
    lower.includes('import_backup.php') ||
    lower.includes('found_dog_notification_test.php') ||
    lower.includes('admin_notification_test.php') ||
    lower.includes('public_dog_profile.php') ||
    lower.includes('report_found_dog.php') ||
    lower.includes('reset_password.php') ||
    lower.includes('verify_2fa.php') ||
    lower.includes('setup_2fa.php') ||
    lower.includes('mailto:') ||
    lower.includes('tel:') ||
    lower.includes('javascript:')
  );
}

async function login(page, username, password) {
  await page.goto(`${baseURL}/login.php`, { waitUntil: 'domcontentloaded' });
  await page.fill('input[name="username"]', username);
  await page.fill('input[name="password"]', password);
  await page.getByRole('button', { name: /login/i }).click();
  await page.waitForLoadState('networkidle').catch(() => {});
  if (/login\.php/i.test(page.url())) {
    throw new Error(`Login failed for ${username}`);
  }
}

async function isVisible(page, selector) {
  return page.locator(selector).first().isVisible().catch(() => false);
}

async function crawlRole(roleName, username, password) {
  if (!username || !password) {
    return { roleName, skipped: true, checked: [], failures: [] };
  }

  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  const page = await browser.newPage({ ignoreHTTPSErrors: true });
  const checked = [];
  const failures = [];
  const visited = new Set();
  const queue = [`${baseURL}/index.php`];

  try {
    await login(page, username, password);

    while (queue.length && visited.size < maxPages) {
      const url = queue.shift();
      if (!url || visited.has(url) || shouldSkip(url)) continue;
      visited.add(url);

      const response = await page.goto(url, { waitUntil: 'domcontentloaded' }).catch(error => {
        failures.push({ role: roleName, url, issue: `navigation failed: ${error.message}` });
        return null;
      });
      if (!response) continue;

      const status = response.status();
      const finalUrl = page.url();
      if (status >= 400 || /login\.php/i.test(finalUrl)) {
        failures.push({ role: roleName, url, issue: `unexpected status/redirect: ${status} ${finalUrl}` });
        continue;
      }

      const contentType = (response.headers()['content-type'] || '').toLowerCase();
      if (!contentType.includes('text/html')) continue;

      const logo = await isVisible(page, 'img.gp-shared-brand-logo[alt="GuidePaw"]');
      const tagline = await page.getByText('Training Trust for the Journey').first().isVisible().catch(() => false);
      const primaryNav = await isVisible(page, 'nav[aria-label="Primary navigation"]');
      const menuButton = await isVisible(page, '#gpMenuOpen');

      checked.push(finalUrl);
      const missing = [];
      if (!logo) missing.push('logo');
      if (!tagline) missing.push('tagline');
      if (!primaryNav) missing.push('primary nav');
      if (!menuButton) missing.push('menu button');
      if (missing.length) {
        failures.push({ role: roleName, url: finalUrl, issue: `missing ${missing.join(', ')}` });
      }

      const links = await page.locator('a[href]').evaluateAll(anchors => anchors.map(a => a.href).filter(Boolean));
      for (const link of links) {
        if (!link.startsWith(baseURL) || shouldSkip(link)) continue;
        const clean = link.split('#')[0];
        if (!visited.has(clean) && !queue.includes(clean)) queue.push(clean);
      }
    }
  } finally {
    await browser.close();
  }

  return { roleName, skipped: false, checked, failures };
}

(async () => {
  const results = [
    await crawlRole('normal', normalUser, normalPass),
    await crawlRole('admin', adminUser, adminPass),
  ];

  for (const result of results) {
    if (result.skipped) {
      console.log(`${result.roleName}: skipped, credentials not set`);
      continue;
    }
    console.log(`${result.roleName}: checked ${result.checked.length} pages`);
    if (result.failures.length) {
      console.table(result.failures);
    }
  }

  const failures = results.flatMap(result => result.failures);
  if (failures.length) {
    process.exitCode = 1;
  }
})().catch(error => {
  console.error(error);
  process.exit(1);
});
