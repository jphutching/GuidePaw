const { test, expect } = require('@playwright/test');

const BASE_URL = process.env.GUIDEPAW_TEST_BASE_URL || 'https://10.147.18.184';
const ADMIN_USERNAME = process.env.GUIDEPAW_ADMIN_TEST_USERNAME || '';
const ADMIN_PASSWORD = process.env.GUIDEPAW_ADMIN_TEST_PASSWORD || '';

const SAFE_ADMIN_PAGES = [
  '/admin.php',
  '/admin_feedback.php',
  '/admin_feedback_ai.php',
  '/admin_beta_requests.php',
  '/admin_feature_roadmap.php',
  '/admin_audit_log.php',
  '/admin_found_dog_reports.php',
  '/admin_notification_test.php',
  '/admin_profile_completion.php',
  '/admin_users.php',
  '/api_tokens.php',
  '/db_status.php',
  '/backup.php'
];

test.setTimeout(120000);

async function loginAsAdmin(page) {
  test.skip(!ADMIN_USERNAME || !ADMIN_PASSWORD, 'Set GUIDEPAW_ADMIN_TEST_USERNAME and GUIDEPAW_ADMIN_TEST_PASSWORD');

  await page.goto(`${BASE_URL}/login.php`);
  await expect(page.locator('input[name="username"]')).toBeVisible();

  await page.fill('input[name="username"]', ADMIN_USERNAME);
  await page.fill('input[name="password"]', ADMIN_PASSWORD);
  await page.getByRole('button', { name: /login/i }).click();

  await page.waitForLoadState('networkidle').catch(() => {});
  await expect(page).toHaveURL(/admin\.php/);
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

async function assertNoVisibleAppErrors(page) {
  const bodyText = await visiblePageTextForErrorScan(page);
  expect(bodyText).not.toMatch(/Fatal error|Parse error|Application Error|SQLSTATE|Undefined function|headers already sent/i);
}

test.describe.serial('GuidePaw admin-safe crawler', () => {
  test('admin login works and dashboard loads', async ({ page }) => {
    await loginAsAdmin(page);

    const response = await page.goto(`${BASE_URL}/admin.php`, { waitUntil: 'domcontentloaded' });
    expect(response.status()).toBeLessThan(400);
    await assertNoVisibleAppErrors(page);

    const bodyText = await page.locator('body').innerText().catch(() => '');
    expect(bodyText).toMatch(/Admin|GuidePaw Admin|Feature|Feedback|Beta/i);
    expect(page.url()).not.toMatch(/login\.php|admin_required/i);
  });

  test('safe admin pages load without visible errors', async ({ page }) => {
    await loginAsAdmin(page);

    const broken = [];

    for (const path of SAFE_ADMIN_PAGES) {
      const url = `${BASE_URL}${path}`;

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

      if (/login\.php|admin_required/i.test(finalUrl)) {
        broken.push({ url, status, detail: `Redirected away from admin page: ${finalUrl}` });
        continue;
      }

      const bodyText = await visiblePageTextForErrorScan(page);
      const errorMatch = bodyText.match(/.{0,120}(Fatal error|Parse error|Application Error|SQLSTATE|Undefined function|headers already sent).{0,240}/is);
      if (errorMatch) {
        broken.push({
          url,
          status,
          detail: `Visible app/PHP error: ${errorMatch[0].replace(/\s+/g, ' ').trim()}`
        });
      }
    }

    console.log(`Checked ${SAFE_ADMIN_PAGES.length} safe admin pages`);
    if (broken.length) {
      console.table(broken);
    }

    expect(broken).toEqual([]);
  });
});
