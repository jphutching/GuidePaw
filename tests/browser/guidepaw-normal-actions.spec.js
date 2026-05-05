const { test, expect } = require('@playwright/test');

const BASE_URL = process.env.GUIDEPAW_TEST_BASE_URL || 'https://10.147.18.184';
const USERNAME = process.env.GUIDEPAW_TEST_USERNAME || '';
const PASSWORD = process.env.GUIDEPAW_TEST_PASSWORD || '';

async function login(page) {
  test.skip(!USERNAME || !PASSWORD, 'Set GUIDEPAW_TEST_USERNAME and GUIDEPAW_TEST_PASSWORD');

  await page.goto(`${BASE_URL}/login.php`);
  await expect(page.locator('input[name="username"]')).toBeVisible();

  await page.fill('input[name="username"]', USERNAME);
  await page.fill('input[name="password"]', PASSWORD);
  await page.getByRole('button', { name: /login/i }).click();
  await page.waitForLoadState('networkidle');
  await expect(page).not.toHaveURL(/login\.php/);
}

async function assertNoVisibleAppErrors(page) {
  const bodyText = await page.locator('body').innerText().catch(() => '');
  expect(bodyText).not.toMatch(/Fatal error|Parse error|Application Error|SQLSTATE|Undefined function/i);
}

async function fillFirst(page, selectors, value) {
  for (const selector of selectors) {
    const locator = page.locator(selector).first();
    if (await locator.count()) {
      if (await locator.isVisible().catch(() => false)) {
        await locator.fill(value);
        return true;
      }
    }
  }
  return false;
}

async function fillRequiredBlanks(page) {
  const inputs = page.locator('input[required], textarea[required]');
  const count = await inputs.count();

  for (let i = 0; i < count; i += 1) {
    const input = inputs.nth(i);

    if (!(await input.isVisible().catch(() => false))) continue;
    if (!(await input.isEditable().catch(() => false))) continue;

    const current = await input.inputValue().catch(() => '');
    if (current) continue;

    const type = (await input.getAttribute('type')) || '';
    const name = ((await input.getAttribute('name')) || '').toLowerCase();

    if (type === 'email' || name.includes('email')) {
      await input.fill('e2e@example.com');
    } else if (type === 'date' || name.includes('date') || name.includes('birth')) {
      await input.fill('2024-01-01');
    } else if (type === 'number' || name.includes('weight')) {
      await input.fill('50');
    } else if (!type || ['text', 'search', 'tel'].includes(type)) {
      await input.fill('E2E Test Value');
    }
  }

  const selects = page.locator('select[required]');
  const selectCount = await selects.count();

  for (let i = 0; i < selectCount; i += 1) {
    const select = selects.nth(i);

    if (!(await select.isVisible().catch(() => false))) continue;

    const current = await select.inputValue().catch(() => '');
    if (current) continue;

    const values = await select.locator('option').evaluateAll(options =>
      options.map(o => o.value).filter(v => v !== '')
    );

    if (values.length) {
      await select.selectOption(values[0]);
    }
  }
}

async function submitCurrentForm(page) {
  const namedButton = page.getByRole('button', { name: /save|add|create|submit|continue/i }).first();
  if (await namedButton.count()) {
    await namedButton.click();
  } else {
    await page.locator('button[type="submit"], input[type="submit"]').first().click();
  }

  await page.waitForLoadState('networkidle').catch(() => {});
}

test.describe.serial('GuidePaw normal user action flows', () => {
  test('normal user can add a test dog', async ({ page }) => {
    await login(page);

    const dogName = `E2E Test Dog ${Date.now()}`;

    await page.goto(`${BASE_URL}/dogs.php`);
    await assertNoVisibleAppErrors(page);

    const addLink = page.getByRole('link', { name: /add.*dog|new.*dog|add profile|create.*dog/i }).first();
    const addButton = page.getByRole('button', { name: /add.*dog|new.*dog|add profile|create.*dog/i }).first();

    if (await addLink.count()) {
      await addLink.click();
    } else if (await addButton.count()) {
      await addButton.click();
    } else {
      await page.goto(`${BASE_URL}/dog_profile.php`);
    }

    await page.waitForLoadState('networkidle').catch(() => {});
    await assertNoVisibleAppErrors(page);

    const filledName = await fillFirst(page, [
      'input[name="name"]',
      'input[name="dog_name"]',
      'input[id*="dog"][id*="name"]',
      'input[placeholder*="Dog"]',
      'input[placeholder*="dog"]'
    ], dogName);

    expect(filledName).toBeTruthy();

    await fillFirst(page, [
      'input[name="breed"]',
      'input[id*="breed"]',
      'input[placeholder*="Breed"]',
      'input[placeholder*="breed"]'
    ], 'Labrador Retriever');

    const breedSelect = page.locator('select[name="breed"], select[id*="breed"]').first();
    if (await breedSelect.count()) {
      const options = await breedSelect.locator('option').evaluateAll(options =>
        options.map(o => ({ value: o.value, text: o.textContent || '' }))
      );
      const lab = options.find(o => /labrador/i.test(o.text) || /labrador/i.test(o.value));
      if (lab) {
        await breedSelect.selectOption(lab.value);
      }
    }

    await fillRequiredBlanks(page);
    await submitCurrentForm(page);
    await assertNoVisibleAppErrors(page);

    const bodyText = await page.locator('body').innerText().catch(() => '');
    expect(bodyText).toContain('E2E Test');
  });

  test('normal user can create a training log entry', async ({ page }) => {
    await login(page);

    await page.goto(`${BASE_URL}/log_entry.php`);
    await page.waitForLoadState('networkidle').catch(() => {});
    await assertNoVisibleAppErrors(page);

    const body = await page.locator('body').innerText().catch(() => '');
    test.skip(/No dogs found|Add a dog profile first/i.test(body), 'No dog available for this test account');

    await fillFirst(page, [
      'input[name="location_name"]',
      'input[id*="location"][id*="name"]'
    ], `E2E Test Location ${Date.now()}`);

    await fillFirst(page, [
      'input[name="location_city_state"]',
      'input[id="city_state"]',
      'input[name*="city"]'
    ], 'Test City, TS');

    await fillFirst(page, [
      'textarea[name="handler_notes"]',
      'textarea[name*="notes"]',
      'textarea'
    ], `E2E test training log created at ${new Date().toISOString()}`);

    const focus = page.locator('input[name="focus_level"]').first();
    if (await focus.count()) {
      await focus.fill('4').catch(async () => {
        await focus.evaluate(el => { el.value = '4'; el.dispatchEvent(new Event('input', { bubbles: true })); });
      });
    }

    const firstSkill = page.locator('input[type="checkbox"][name="skills[]"]').first();
    if (await firstSkill.count()) {
      await firstSkill.check().catch(() => {});
    }

    const mediaInput = page.locator('input[type="file"][name="training_media"]').first();
    if (await mediaInput.count()) {
      const accept = (await mediaInput.getAttribute('accept')) || '';
      if (/image|\.(jpg|jpeg|png|webp)/i.test(accept)) {
        await mediaInput.setInputFiles('tests/fixtures/e2e-test-image.png');
      }
    }

    await fillRequiredBlanks(page);
    await submitCurrentForm(page);
    await assertNoVisibleAppErrors(page);

    const afterText = await page.locator('body').innerText().catch(() => '');
    expect(afterText).toMatch(/Training log saved|Training History|E2E test training log|Log Training/i);
  });

  test('training history export links download files', async ({ page }) => {
    await login(page);

    await page.goto(`${BASE_URL}/training_history.php?status=active`);
    await page.waitForLoadState('networkidle').catch(() => {});
    await assertNoVisibleAppErrors(page);

    const exportLinks = page.locator('a[href*="training_history_export.php"]');
    const count = await exportLinks.count();

    test.skip(count === 0, 'No training history export links found for this account');

    const [download] = await Promise.all([
      page.waitForEvent('download'),
      exportLinks.first().click()
    ]);

    const suggested = download.suggestedFilename();
    expect(suggested).toBeTruthy();
  });
});
