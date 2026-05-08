const { chromium } = require('playwright');

const baseURL = process.env.GUIDEPAW_TEST_BASE_URL || process.env.GUIDEPAW_BASE_URL || 'https://beta.guidepaw.app';
const normalUser = process.env.GUIDEPAW_TEST_USERNAME || '';
const normalPass = process.env.GUIDEPAW_TEST_PASSWORD || '';
const adminUser = process.env.GUIDEPAW_ADMIN_TEST_USERNAME || '';
const adminPass = process.env.GUIDEPAW_ADMIN_TEST_PASSWORD || '';
const maxPages = Number(process.env.GUIDEPAW_RESPONSIVE_MAX_PAGES || 90);
const maxFindingsPerPage = Number(process.env.GUIDEPAW_RESPONSIVE_MAX_FINDINGS_PER_PAGE || 4);
const viewports = [
  { name: 'mobile-320', width: 320, height: 720 },
  { name: 'mobile-375', width: 375, height: 812 },
  { name: 'tablet-768', width: 768, height: 1024 },
  { name: 'desktop-1366', width: 1366, height: 900 },
];

function shouldSkip(rawUrl) {
  const lower = rawUrl.toLowerCase();
  return (
    lower.includes('logout.php') ||
    lower.includes('delete') ||
    lower.includes('remove') ||
    lower.includes('archive') ||
    lower.includes('export') ||
    lower.includes('/uploads/') ||
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

async function collectUrls(username, password) {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  const page = await browser.newPage({ ignoreHTTPSErrors: true });
  const urls = [];
  const visited = new Set();
  const queue = [`${baseURL}/index.php`];

  try {
    await login(page, username, password);
    while (queue.length && visited.size < maxPages) {
      const url = queue.shift();
      if (!url || visited.has(url) || shouldSkip(url)) continue;
      visited.add(url);

      const response = await page.goto(url, { waitUntil: 'domcontentloaded' }).catch(() => null);
      if (!response || response.status() >= 400 || /login\.php/i.test(page.url())) continue;
      const contentType = (response.headers()['content-type'] || '').toLowerCase();
      if (!contentType.includes('text/html')) continue;

      const cleanUrl = page.url().split('#')[0];
      if (!urls.includes(cleanUrl)) urls.push(cleanUrl);

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

  return urls;
}

async function checkUrl(context, roleName, url, viewport) {
  const page = await context.newPage();
  try {
    const response = await page.goto(url, { waitUntil: 'domcontentloaded' }).catch(error => ({ error }));
    if (!response || response.error) {
      return [{ role: roleName, viewport: viewport.name, url, issue: `navigation failed: ${response?.error?.message || 'unknown'}` }];
    }
    await page.waitForLoadState('networkidle').catch(() => {});

    return await page.evaluate(({ roleName, viewportName, url, maxFindingsPerPage }) => {
      const failures = [];
      const doc = document.documentElement;
      const body = document.body;
      const scrollWidth = Math.max(doc.scrollWidth, body ? body.scrollWidth : 0);
      const clientWidth = doc.clientWidth;
      if (scrollWidth > clientWidth + 2) {
        failures.push({
          role: roleName,
          viewport: viewportName,
          url,
          issue: `horizontal page overflow ${scrollWidth}px > ${clientWidth}px`,
        });
      }

      const selectors = 'main, .container, .container-fluid, .wrap, .card, .cardx, table, form, input, textarea, select, button, a, h1, h2, h3, p, pre, code';
      const elements = Array.from(document.querySelectorAll(selectors));
      for (const el of elements) {
        const style = window.getComputedStyle(el);
        if (style.display === 'none' || style.visibility === 'hidden' || style.position === 'fixed') continue;
        const rect = el.getBoundingClientRect();
        if (rect.width <= 0 || rect.height <= 0) continue;
        if (rect.left < -2 || rect.right > clientWidth + 2) {
          const label = (el.textContent || el.getAttribute('aria-label') || el.getAttribute('name') || el.tagName || '')
            .trim()
            .replace(/\s+/g, ' ')
            .slice(0, 80);
          failures.push({
            role: roleName,
            viewport: viewportName,
            url,
            issue: `${el.tagName.toLowerCase()} overflows viewport: left ${Math.round(rect.left)}, right ${Math.round(rect.right)}, width ${Math.round(rect.width)}${label ? ` "${label}"` : ''}`,
          });
          if (failures.length >= maxFindingsPerPage) break;
        }
      }
      return failures;
    }, { roleName, viewportName: viewport.name, url, maxFindingsPerPage });
  } finally {
    await page.close();
  }
}

async function checkRole(roleName, username, password) {
  if (!username || !password) {
    console.log(`${roleName}: skipped, credentials not set`);
    return [];
  }

  const urls = await collectUrls(username, password);
  console.log(`${roleName}: collected ${urls.length} pages`);
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  const failures = [];
  try {
    for (const viewport of viewports) {
      const context = await browser.newContext({ ignoreHTTPSErrors: true, viewport });
      const page = await context.newPage();
      await login(page, username, password);
      await page.close();

      for (const url of urls) {
        failures.push(...await checkUrl(context, roleName, url, viewport));
      }
      await context.close();
      console.log(`${roleName}: checked ${urls.length} pages at ${viewport.name}`);
    }
  } finally {
    await browser.close();
  }
  return failures;
}

(async () => {
  const failures = [
    ...await checkRole('normal', normalUser, normalPass),
    ...await checkRole('admin', adminUser, adminPass),
  ];

  if (failures.length) {
    console.table(failures.slice(0, 100));
    console.error(`${failures.length} responsive overflow findings`);
    process.exitCode = 1;
  } else {
    console.log('No responsive overflow findings.');
  }
})().catch(error => {
  console.error(error);
  process.exit(1);
});
