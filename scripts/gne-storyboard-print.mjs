import { pathToFileURL } from 'node:url';
import { chromium } from 'playwright';

const [source, output] = process.argv.slice(2);

if (!source || !output) {
    throw new Error('Usage: node gne-storyboard-print.mjs <source-html> <output-pdf>');
}

const browser = await chromium.launch({ headless: true });

try {
    const page = await browser.newPage();
    await page.goto(pathToFileURL(source).href, { waitUntil: 'load' });
    await page.pdf({
        path: output,
        format: 'A4',
        landscape: true,
        printBackground: true,
        margin: { top: '8mm', right: '8mm', bottom: '8mm', left: '8mm' },
    });
} finally {
    await browser.close();
}
