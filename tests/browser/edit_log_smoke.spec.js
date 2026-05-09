const { test, expect } = require('@playwright/test');

const baseURL = process.env.GUIDEPAW_BASE_URL || process.env.GUIDEPAW_TEST_BASE_URL || 'http://localhost';
const username = process.env.GUIDEPAW_ADMIN_TEST_USERNAME || process.env.GUIDEPAW_TEST_USERNAME;
const password = process.env.GUIDEPAW_ADMIN_TEST_PASSWORD || process.env.GUIDEPAW_TEST_PASSWORD;

test.describe('GuidePaw training log editing smoke test', () => {
  test.skip(!username || !password, 'Set admin or regular smoke credentials to run this smoke test.');

  test('test account can edit a training log from history', async ({ page }) => {
    const stamp = Date.now();
    const newLocationName = `QA Edited Log ${stamp}`;
    const newNotes = `QA edit smoke note ${stamp}`;

    await page.goto(`${baseURL}/login.php`);
    await expect(page.getByRole('heading', { name: /handler login/i })).toBeVisible();
    await page.getByPlaceholder(/username/i).fill(username);
    await page.getByPlaceholder(/password/i).fill(password);
    await page.getByRole('button', { name: /^login$/i }).click();
    await page.waitForLoadState('networkidle');
    await expect(page).not.toHaveURL(/login\.php/);

    await page.goto(`${baseURL}/dogs.php`);
    await expect(page.getByRole('heading', { name: /dog profiles/i })).toBeVisible();
    const useLink = page.getByRole('link', { name: /^use$/i }).first();
    await expect(useLink).toBeVisible();
    await useLink.click();
    await page.waitForLoadState('networkidle');

    await page.goto(`${baseURL}/log_entry.php`);
    await expect(page.getByRole('heading', { name: /log training/i })).toBeVisible();
    await page.locator('input[name="location_name"]').fill(`QA Original Log ${stamp}`);
    await page.locator('input[name="location_city_state"]').fill('Denver, CO');
    await page.locator('select[name="location_type"]').selectOption('Public Store');
    await page.locator('input[name="focus_level"]').evaluate((el) => {
      el.value = '3';
      el.dispatchEvent(new Event('input', { bubbles: true }));
      el.dispatchEvent(new Event('change', { bubbles: true }));
    });
    await page.locator('textarea[name="handler_notes"]').fill(`Original QA note ${stamp}`);
    await page.getByRole('button', { name: /save training log/i }).click();
    await page.waitForLoadState('networkidle');
    await expect(page).toHaveURL(/view_logs\.php/);

    await page.goto(`${baseURL}/view_logs.php`);
    await expect(page.getByRole('heading', { name: /training history/i })).toBeVisible();

    const logCard = page.locator('article', { hasText: `QA Original Log ${stamp}` }).first();
    await expect(logCard).toBeVisible();
    const logCardId = await logCard.getAttribute('id');
    expect(logCardId).toMatch(/^log-\d+$/);
    const editHref = `edit_log.php?id=${logCardId.replace(/^log-/, '')}`;

    await page.goto(new URL(editHref, baseURL).toString());
    await expect(page.getByRole('heading', { name: /edit training log/i })).toBeVisible();
    await expect(page.locator('input[name="location_name"]')).toBeVisible();
    await expect(page.locator('input[name="log_date"]')).toBeVisible();
    await expect(page.locator('select[name="location_type"]')).toBeVisible();
    await expect(page.locator('input[name="focus_level"]')).toBeVisible();
    await expect(page.locator('textarea[name="handler_notes"]')).toBeVisible();

    await page.locator('input[name="location_name"]').fill(newLocationName);
    await page.locator('textarea[name="handler_notes"]').fill(newNotes);
    await page.locator('input[name="location_city_state"]').fill('Denver, CO');
    await page.locator('select[name="location_type"]').selectOption('Public Store');
    await page.locator('input[name="focus_level"]').evaluate((el) => {
      el.value = '4';
      el.dispatchEvent(new Event('input', { bubbles: true }));
      el.dispatchEvent(new Event('change', { bubbles: true }));
    });

    const editLogId = await page.locator('input[name="id"]').getAttribute('value');
    expect(editLogId).toMatch(/^\d+$/);

    await page.getByRole('button', { name: /update log entry/i }).click();
    await page.waitForLoadState('networkidle');
    await expect(page).toHaveURL(/view_logs\.php\?status=updated/);

    const historyText = await page.locator('body').innerText();
    expect(historyText).toContain(newLocationName);
    expect(historyText).toContain(newNotes);
  });
});
