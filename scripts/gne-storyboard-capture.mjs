import { readFile, writeFile } from 'node:fs/promises';
import { pathToFileURL } from 'node:url';
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

    const root = process.env.GNE_STORYBOARD_MANIFEST.replace('/manifest.json', '');
    const escape = (value) => String(value ?? '').replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;');
    const pages = manifest.frames.map((frame) => `<section class="page"><header><span>${escape(frame.act)} · ${frame.sequence}</span><h1>${escape(frame.title)}</h1></header><div class="layout"><img src="${escape(frame.capture_filename)}" alt="${escape(frame.title)}"><aside><h2>${escape(frame.persona)}</h2><p>${escape(frame.action)}</p><dl><dt>Business route</dt><dd>${escape(frame.route)}</dd><dt>Expected state</dt><dd>${escape(frame.expected)}</dd><dt>Lifecycle</dt><dd>${escape(frame.snapshot.lifecycle.current_stage ?? 'not started')}</dd><dt>Next stage</dt><dd>${escape(frame.snapshot.lifecycle.next_stage ?? 'complete')}</dd></dl><p class="hash">Repository ${escape(frame.snapshot.repository_fingerprint)}</p></aside></div></section>`).join('');
    const html = `<!doctype html><html><head><meta charset="utf-8"><style>@page{size:A4 landscape;margin:10mm}*{box-sizing:border-box}body{margin:0;font-family:Arial;color:#17323a}.page{page-break-after:always;height:185mm;display:flex;flex-direction:column}.cover{justify-content:center;background:#17323a;color:white;padding:30mm}header{border-bottom:2px solid #b76025;margin-bottom:6mm}header span{color:#9a4b14;font-weight:bold;text-transform:uppercase}h1{font-size:26px;margin:2mm 0 4mm}.layout{display:grid;grid-template-columns:1.65fr 1fr;gap:8mm;min-height:0;flex:1}img{width:100%;height:100%;object-fit:contain;border:1px solid #cad7da;background:#eef3f4}aside{font-size:12px}dt{font-weight:bold;margin-top:4mm}dd{margin:1mm 0}.hash{font:8px monospace;overflow-wrap:anywhere}</style></head><body><section class="page cover"><p>GNE Repository-Native Business Compiler</p><h1>${escape(manifest.title)}</h1><p>${manifest.frames.length} authenticated frames · fictional demonstration evidence · storyboard observation only</p></section>${pages}</body></html>`;
    const htmlPath = `${root}/storyboard.html`;
    await writeFile(htmlPath, html);
    await page.goto(pathToFileURL(htmlPath).href, { waitUntil: 'load' });
    await page.pdf({ path: `${root}/pdf/${manifest.identifier}.pdf`, format: 'A4', landscape: true, printBackground: true, margin: { top: '10mm', right: '10mm', bottom: '10mm', left: '10mm' } });
} finally {
    await browser.close();
}
