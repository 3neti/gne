import { readFile } from 'node:fs/promises';
import { chromium } from 'playwright';

const manifest = JSON.parse(await readFile(process.env.GNE_STORYBOARD_MANIFEST, 'utf8'));
const baseUrl = process.env.GNE_STORYBOARD_BASE_URL.replace(/\/$/, '');
const browser = await chromium.launch({ headless: true });

try {
    const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
    await page.goto(`${baseUrl}/login`, { waitUntil: 'networkidle' });
    await page.screenshot({ path: `${process.env.GNE_STORYBOARD_MANIFEST.replace('/manifest.json', '')}/${manifest.frames[0].capture_filename}`, fullPage: true });
    await page.locator('input[name="email"]').fill(process.env.GNE_STORYBOARD_EMAIL);
    await page.locator('input[name="password"]').fill(process.env.GNE_STORYBOARD_PASSWORD);
    await Promise.all([
        page.waitForURL('**/dashboard'),
        page.locator('[data-test="login-button"]').click(),
    ]);
    await page.waitForLoadState('networkidle');

    if (page.url().includes('/login')) {
throw new Error(`Storyboard authentication failed: ${(await page.locator('body').innerText()).slice(0, 500)}`);
}

    for (const frame of manifest.frames.slice(1)) {
        const response = await page.goto(`${baseUrl}${frame.capture_route}`, { waitUntil: 'networkidle' });

        if (!response?.ok()) {
throw new Error(`Frame ${frame.identifier} route returned ${response?.status() ?? 'no response'} at ${page.url()}`);
}

        await page.locator(`[data-storyboard-frame="${frame.identifier}"]`).waitFor();
        const body = await page.locator('body').innerText();

        if (!body.includes(frame.expected)) {
throw new Error(`Frame ${frame.identifier} does not contain expected marker: ${frame.expected}`);
}

        await page.screenshot({ path: `${process.env.GNE_STORYBOARD_MANIFEST.replace('/manifest.json', '')}/${frame.capture_filename}`, fullPage: true });
    }
} finally {
    await browser.close();
}
