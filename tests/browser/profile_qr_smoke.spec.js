const { test, expect } = require('@playwright/test');

const baseURL = process.env.GUIDEPAW_BASE_URL || process.env.GUIDEPAW_TEST_BASE_URL || 'http://localhost';
const username = process.env.GUIDEPAW_TEST_USERNAME;
const password = process.env.GUIDEPAW_TEST_PASSWORD;

test.describe('GuidePaw profile and public QR smoke test', () => {
  test.skip(!username || !password, 'Set GUIDEPAW_TEST_USERNAME and GUIDEPAW_TEST_PASSWORD to run this smoke test.');

  test('test account can update handler profile, copy defaults, and view public dog profile', async ({ page, context }) => {
    await page.goto(`${baseURL}/login.php`);
    await expect(page.getByRole('heading', { name: /handler login/i })).toBeVisible();
    await page.getByPlaceholder(/username/i).fill(username);
    await page.getByPlaceholder(/password/i).fill(password);
    await page.getByRole('button', { name: /^login$/i }).click();
    await page.waitForLoadState('networkidle');
    await expect(page).not.toHaveURL(/login\.php/);

    await page.goto(`${baseURL}/handler_profile.php`);
    await expect(page.getByRole('heading', { name: /handler profile/i })).toBeVisible();
    await page.goto(`${baseURL}/dog_profile.php`);
    await expect(page.getByText(/public qr profile/i).first()).toBeVisible();
    await expect(page.getByText(/private dog details/i)).toBeVisible();
    await expect(page.getByText(/public qr profile details/i)).toBeVisible();

    const useDefaults = page.getByRole('button', { name: /use handler profile info/i });
    if (await useDefaults.isVisible()) {
      await useDefaults.click();
      await expect(page.getByText(/handler profile defaults applied/i)).toBeVisible();
    }

    await expect(page.locator('input[name="handler_name"]')).toHaveValue(/\S+/);
    await expect(page.locator('input[name="handler_phone"]')).toHaveValue(/\S+/);
    await expect(page.locator('input[name="handler_email"]')).toHaveValue(/\S+/);

    const previewLink = page.getByRole('link', { name: /preview public profile/i });
    await expect(previewLink).toBeVisible();
    const publicHref = await previewLink.getAttribute('href');
    expect(publicHref).toMatch(/public_dog_profile\.php\?dog=\d+&token=/);
    const qrTrackingLink = page.getByRole('link', { name: /qr tracking/i });
    await expect(qrTrackingLink).toBeVisible();
    const qrTrackingHref = await qrTrackingLink.getAttribute('href');
    expect(qrTrackingHref).toMatch(/qr_tracking\.php\?dog_id=\d+/);

    await page.goto(new URL(qrTrackingHref, baseURL).toString());
    await expect(page.getByRole('heading', { name: /qr tracking/i })).toBeVisible();
    const beforeText = await page.locator('body').innerText();
    const beforeCount = Number((beforeText.match(/QR opens tracked\s+(\d+)/i) || [])[1] || 0);

    const publicPage = await context.newPage();
    await publicPage.goto(new URL(publicHref, baseURL).toString());
    await expect(publicPage.getByText(/guidepaw public service dog profile/i)).toBeVisible();
    await expect(publicPage.getByText(/handler contact/i)).toBeVisible();
    await expect(publicPage.getByText(/private app notes/i)).toHaveCount(0);

    await page.goto(new URL(qrTrackingHref, baseURL).toString());
    const afterText = await page.locator('body').innerText();
    const afterCount = Number((afterText.match(/QR opens tracked\s+(\d+)/i) || [])[1] || 0);
    expect(afterCount).toBeGreaterThan(beforeCount);
  });
});
