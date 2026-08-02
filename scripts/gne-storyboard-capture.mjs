import { readFile } from 'node:fs/promises';
import { chromium } from 'playwright';

const manifestPath = process.env.GNE_STORYBOARD_MANIFEST;
const manifest = JSON.parse(await readFile(manifestPath, 'utf8'));
const root = manifestPath.replace('/manifest.json', '');
const baseUrl = process.env.GNE_STORYBOARD_BASE_URL.replace(/\/$/, '');
const browser = await chromium.launch({ headless: true });
const report = {
    authentication: {
        mode: 'interactive_login',
        login_route: '/login',
        login_submitted: false,
        authenticated_redirect: null,
        session_preserved: false,
        protected_frame_count: 0,
        unexpected_login_redirects: 0,
    },
    frames: [],
};

const pathOf = (url) => new URL(url).pathname;

try {
    const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
    const page = await context.newPage();
    const loginFrame = manifest.frames[0];
    const loginResponse = await page.goto(`${baseUrl}${loginFrame.capture_route}`, { waitUntil: 'networkidle' });
    const loginBody = await page.locator('body').innerText();

    if (!loginResponse?.ok() || pathOf(page.url()) !== loginFrame.expected_final_route || !loginBody.includes(loginFrame.expected)) {
        throw new Error('The public login frame did not reach and verify the real login page.');
    }

    await page.screenshot({ path: `${root}/${loginFrame.capture_filename}`, fullPage: true });
    report.frames.push({
        identifier: loginFrame.identifier,
        status: 'captured_and_verified',
        final_route: pathOf(page.url()),
        http_status: loginResponse.status(),
        expected_marker_verified: true,
        authenticated: false,
    });

    await page.locator('input[name="email"]').fill(process.env.GNE_STORYBOARD_EMAIL);
    await page.locator('input[name="password"]').fill(process.env.GNE_STORYBOARD_PASSWORD);
    await Promise.all([
        page.waitForURL('**/dashboard'),
        page.locator('[data-test="login-button"]').click(),
    ]);
    await page.waitForLoadState('networkidle');
    report.authentication.login_submitted = true;
    report.authentication.authenticated_redirect = pathOf(page.url());

    if (report.authentication.authenticated_redirect !== '/dashboard') {
        throw new Error(`Interactive login redirected to ${report.authentication.authenticated_redirect}, not /dashboard.`);
    }

    for (const frame of manifest.frames.slice(1)) {
        await page.setExtraHTTPHeaders({
            'X-GNE-Storyboard': manifest.identifier,
            'X-GNE-Storyboard-Stage': frame.stage,
            ...(frame.capture_type === 'document' ? { 'X-Livewire-Navigate': '1' } : {}),
        });
        const response = await page.goto(`${baseUrl}${frame.capture_route}`, { waitUntil: 'networkidle' });
        const finalRoute = pathOf(page.url());
        const body = await page.locator('body').innerText();

        if (finalRoute === '/login') {
            report.authentication.unexpected_login_redirects += 1;

            throw new Error(`Protected frame ${frame.identifier} unexpectedly redirected to login.`);
        }

        if (!response?.ok() || response.status() === 403 || finalRoute !== frame.expected_final_route) {
            throw new Error(`Frame ${frame.identifier} failed route verification: ${response?.status() ?? 'no response'} at ${finalRoute}.`);
        }

        if (body.includes('Whoops') || body.includes('Server Error') || body.includes('403 Forbidden')) {
            throw new Error(`Frame ${frame.identifier} displayed an exception or denial page.`);
        }

        if (!body.includes(frame.expected)) {
            throw new Error(`Frame ${frame.identifier} does not contain expected marker: ${frame.expected}. Visible text: ${body.slice(0, 500)}`);
        }

        if (frame.identifier === 'invoice-r2' && body.includes('50000')) {
            throw new Error('Invoice revision 2 still presents the superseded 50000 amount as current content.');
        }

        await page.screenshot({ path: `${root}/${frame.capture_filename}`, fullPage: true });
        report.frames.push({
            identifier: frame.identifier,
            status: 'captured_and_verified',
            final_route: finalRoute,
            http_status: response.status(),
            expected_marker_verified: true,
            authenticated: true,
        });
        report.authentication.protected_frame_count += 1;
    }

    report.authentication.session_preserved = report.authentication.protected_frame_count === manifest.frames.length - 1
        && report.authentication.unexpected_login_redirects === 0;
    console.log(JSON.stringify(report));
} finally {
    await browser.close();
}
