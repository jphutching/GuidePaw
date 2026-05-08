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
    await expect(page).toHaveURL(/index\.php|\/$/);

    await page.goto(`${baseURL}/handler_profile.php`);
    await expect(page.getByRole('heading', { name: /handler profile/i })).toBeVisible();
    await page.locator('input[name="display_name"]').fill('GuidePaw Test Handler');
    await page.locator('input[name="phone"]').fill('555-010-0000');
    await page.locator('input[name="public_email"]').fill('test-handler@example.com');
    await page.locator('input[name="backup_contact_name"]').fill('GuidePaw Backup');
    await page.locator('input[name="backup_contact_phone"]').fill('555-010-0001');
    await page.locator('textarea[name="public_notes"]').fill('QA smoke test public handler note.');
    await page.getByRole('button', { name: /save handler profile/i }).click();
    await expect(page.getByText(/handler profile saved/i)).toBeVisible();

    await page.goto(`${baseURL}/dog_profile.php`);
    await expect(page.getByText(/public qr profile/i).first()).toBeVisible();
    await expect(page.getByText(/private dog details/i)).toBeVisible();
    await expect(page.getByText(/public qr profile details/i)).toBeVisible();

    const useDefaults = page.getByRole('button', { name: /use handler profile info/i });
    if (await useDefaults.isVisible()) {
      await useDefaults.click();
      await expect(page.getByText(/handler profile defaults applied/i)).toBeVisible();
    }

    await expect(page.locator('input[name="handler_name"]')).toHaveValue(/GuidePaw Test Handler/);
    await expect(page.locator('input[name="handler_phone"]')).toHaveValue(/555-010-0000/);
    await expect(page.locator('input[name="handler_email"]')).toHaveValue(/test-handler@example\.com/);

    const previewLink = page.getByRole('link', { name: /preview public profile/i });
    await expect(previewLink).toBeVisible();
    const publicHref = await previewLink.getAttribute('href');
    expect(publicHref).toMatch(/public_dog_profile\.php\?dog=\d+&token=/);

    await page.goto(`${baseURL}/logout.php`);
    await expect(page).toHaveURL(/login\.php/);

    const publicPage = await context.newPage();
    await publicPage.goto(new URL(publicHref, baseURL).toString());
    await expect(publicPage.getByText(/guidepaw public service dog profile/i)).toBeVisible();
    await expect(publicPage.getByText(/handler contact/i)).toBeVisible();
    await expect(publicPage.getByText(/GuidePaw Test Handler/)).toBeVisible();
    await expect(publicPage.getByText(/private app notes/i)).toHaveCount(0);
  });
});
