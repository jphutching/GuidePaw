const { test, expect } = require('@playwright/test');

const BASE_URL = process.env.GUIDEPAW_TEST_BASE_URL || 'https://10.147.18.184';
const USERNAME = process.env.GUIDEPAW_TEST_USERNAME || '';
const PASSWORD = process.env.GUIDEPAW_TEST_PASSWORD || '';

const ADMIN_ONLY_PAGES = [
  '/admin.php',
  '/admin_feedback.php',
  '/admin_beta_requests.php',
  '/admin_feature_roadmap.php',
  '/admin_audit_log.php',
  '/db_status.php',
  '/admin_users.php'
];

async function loginAsNormalUser(page) {
  test.skip(!USERNAME || !PASSWORD, 'Set GUIDEPAW_TEST_USERNAME and GUIDEPAW_TEST_PASSWORD');

  await page.goto(`${BASE_URL}/login.php`);
  await expect(page.locator('input[name="username"]')).toBeVisible();

  await page.fill('input[name="username"]', USERNAME);
  await page.fill('input[name="password"]', PASSWORD);
  await page.getByRole('button', { name: /login/i }).click();

  await page.waitForLoadState('networkidle').catch(() => {});
  await expect(page).not.toHaveURL(/login\.php/);
}

test.describe('GuidePaw normal-user admin protection', () => {
  test('normal user cannot access admin-only pages', async ({ page }) => {
    await loginAsNormalUser(page);

    const failures = [];

    for (const path of ADMIN_ONLY_PAGES) {
      const url = `${BASE_URL}${path}`;
      const response = await page.goto(url, { waitUntil: 'domcontentloaded' }).catch(error => {
        failures.push({ url, status: 'NAV_ERROR', detail: error.message });
        return null;
      });

      if (!response) continue;

      const status = response.status();
      const finalUrl = page.url();
      const bodyText = await page.locator('body').innerText().catch(() => '');

      const blockedByStatus = status === 401 || status === 403;
      const blockedByRedirectOrMessage =
        /login\.php|admin_required|permission|not authorized|not allowed|access denied/i.test(finalUrl) ||
        /admin_required|permission|not authorized|not allowed|access denied|do not have permission/i.test(bodyText);

      const blocked = blockedByStatus || blockedByRedirectOrMessage;

      // If the server returned 401/403, access control worked even if the error page
      // still contains words like "Admin" in its title or message.
      const visiblyAdmin =
        status < 400 &&
        /Admin|Beta Requests|Feature Roadmap|Audit Log|Database Status|Users/i.test(bodyText) &&
        !/admin_required|permission|not authorized|not allowed|access denied|do not have permission/i.test(bodyText);

      if (!blocked || visiblyAdmin) {
        failures.push({
          url,
          status,
          detail: `Normal user was not clearly blocked. Final URL: ${finalUrl}`
        });
      }
    }

    console.log(`Checked ${ADMIN_ONLY_PAGES.length} admin-only pages against normal user`);
    if (failures.length) {
      console.table(failures);
    }

    expect(failures).toEqual([]);
  });
});
