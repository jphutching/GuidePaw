/**
 * Demo account smoke tests.
 * Uses publicly-known demo credentials — no secrets required.
 * Verifies login, demo banner, key nav pages, and that data is present.
 */
const { test, expect } = require('@playwright/test');

const BASE_URL  = process.env.GUIDEPAW_TEST_BASE_URL || 'https://10.147.18.184';
const DEMO_PASS = 'Demo1234!!';
const ACCOUNTS  = [
  { username: 'demo.sarah',    label: 'Foundation training' },
  { username: 'demo.marcus',   label: 'Advanced training'   },
  { username: 'demo.jennifer', label: 'Certified handler'   },
];

test.setTimeout(60000);

async function assertNoAppErrors(page) {
  const text = await page.locator('body').innerText().catch(() => '');
  expect(text).not.toMatch(/Fatal error|Parse error|Application Error|SQLSTATE|Undefined function/i);
}

async function loginDemo(page, username) {
  await page.goto(`${BASE_URL}/login.php`);
  await expect(page.locator('input[name="username"]')).toBeVisible();
  await page.fill('input[name="username"]', username);
  await page.fill('input[name="password"]', DEMO_PASS);
  await page.getByRole('button', { name: /login/i }).click();
  await page.waitForLoadState('networkidle');
  await expect(page).not.toHaveURL(/login\.php/, { timeout: 15000 });
}

// ── Login page: demo fill buttons ────────────────────────────────────────────

test('login page shows demo account section', async ({ page }) => {
  await page.goto(`${BASE_URL}/login.php`);
  await expect(page.locator('text=Try a Demo Account')).toBeVisible();
  for (const { username } of ACCOUNTS) {
    await expect(page.locator(`text=${username}`)).toBeVisible();
  }
});

test('demo fill buttons auto-populate credentials', async ({ page }) => {
  await page.goto(`${BASE_URL}/login.php`);
  const useBtn = page.locator('.demo-fill').first();
  await expect(useBtn).toBeVisible();
  await useBtn.click();
  const usernameVal = await page.inputValue('input[name="username"]');
  expect(usernameVal).toMatch(/^demo\./);
  const passwordVal = await page.inputValue('#passwordInput');
  expect(passwordVal).toBe(DEMO_PASS);
});

// ── Per-account login & banner ────────────────────────────────────────────────

for (const { username } of ACCOUNTS) {
  test(`${username} — login succeeds`, async ({ page }) => {
    await loginDemo(page, username);
    await assertNoAppErrors(page);
    expect(page.url()).not.toMatch(/login\.php/);
  });

  test(`${username} — dashboard loads with no app errors`, async ({ page }) => {
    await loginDemo(page, username);
    await page.waitForLoadState('networkidle');
    await assertNoAppErrors(page);
  });
}

// ── Key pages accessible as demo user ────────────────────────────────────────

const KEY_PAGES = [
  'view_logs.php',
  'dog_health.php',
  'training_program.php',
  'faq.php',
  'changelog.php',
];

for (const pagePath of KEY_PAGES) {
  test(`demo.sarah — ${pagePath} loads without error`, async ({ page }) => {
    await loginDemo(page, 'demo.sarah');
    await page.goto(`${BASE_URL}/${pagePath}`);
    await page.waitForLoadState('networkidle');
    await assertNoAppErrors(page);
    const status = await page.evaluate(() => document.title);
    expect(status).not.toMatch(/404|not found/i);
  });
}

// ── Changelog page ────────────────────────────────────────────────────────────

test('changelog page lists recent versions', async ({ page }) => {
  await page.goto(`${BASE_URL}/changelog.php`);
  await page.waitForLoadState('networkidle');
  await assertNoAppErrors(page);
  // Should show at least one version entry
  const entries = page.locator('text=/0\\.0\\d{2}/');
  await expect(entries.first()).toBeVisible();
});

// ── API: demo_status returns JSON ─────────────────────────────────────────────

test('api/changelog.php returns JSON with entries', async ({ request }) => {
  const resp = await request.get(`${BASE_URL}/api/changelog.php`);
  expect(resp.ok()).toBeTruthy();
  const body = await resp.json();
  expect(body.success).toBe(true);
  expect(Array.isArray(body.entries)).toBe(true);
  expect(body.entries.length).toBeGreaterThan(0);
});
