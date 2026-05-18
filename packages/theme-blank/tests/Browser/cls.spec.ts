import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

test('base 1column layout produces zero CLS through load + idle', async ({ page }) => {
  await page.addInitScript(() => {
    (window as any).__clsEntries = [];
    const observer = new PerformanceObserver((list) => {
      for (const entry of list.getEntries()) {
        if (!(entry as any).hadRecentInput) {
          (window as any).__clsEntries.push((entry as any).value);
        }
      }
    });
    observer.observe({ type: 'layout-shift', buffered: true });
  });

  const fixtureHtml = readFileSync(
    resolve(__dirname, 'fixtures/base-page.html'),
    'utf-8',
  );
  await page.setContent(fixtureHtml, { waitUntil: 'networkidle' });
  await page.waitForTimeout(500);

  const cls = await page.evaluate(() => {
    const entries: number[] = (window as any).__clsEntries ?? [];
    return entries.reduce((acc, v) => acc + v, 0);
  });

  expect(cls).toBe(0);
});
