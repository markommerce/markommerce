import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

const fixtureHtml = readFileSync(
  resolve(__dirname, 'fixtures/primitives-page.html'),
  'utf-8',
);

test.describe('primitives CLS', () => {
  test('all 12 primitives produce zero CLS in unupgraded state', async ({ page }) => {
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
    await page.setContent(fixtureHtml, { waitUntil: 'networkidle' });
    await page.waitForTimeout(500);
    const cls = await page.evaluate(() => {
      const entries: number[] = (window as any).__clsEntries ?? [];
      return entries.reduce((acc, v) => acc + v, 0);
    });
    expect(cls).toBe(0);
  });

  test('all 12 primitives produce zero CLS through upgrade', async ({ page }) => {
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
    await page.setContent(fixtureHtml, { waitUntil: 'networkidle' });

    await page.evaluate(() => {
      const lengthAttrMap: Record<string, { attr: string; varName: string }> = {
        'mk-grid': { attr: 'min', varName: '--mk-grid-min' },
        'mk-sidebar': { attr: 'width', varName: '--mk-sidebar-width' },
        'mk-switcher': { attr: 'threshold', varName: '--mk-switcher-threshold' },
        'mk-cover': { attr: 'min-height', varName: '--mk-cover-min-height' },
      };
      const tags = [
        'mk-stack', 'mk-cluster', 'mk-grid', 'mk-container',
        'mk-sidebar', 'mk-switcher', 'mk-cover', 'mk-divider',
        'mk-heading', 'mk-text', 'mk-link', 'mk-badge',
      ];
      for (const tag of tags) {
        if (customElements.get(tag)) continue;
        const sync = lengthAttrMap[tag];
        customElements.define(tag, class extends HTMLElement {
          connectedCallback() {
            if (sync) {
              const value = this.getAttribute(sync.attr);
              if (value !== null && value !== '') {
                this.style.setProperty(sync.varName, value);
              }
            }
            // Mirror Lit's ChildPart marker
            this.insertBefore(document.createComment(''), this.firstChild);
          }
        });
      }
    });
    await page.waitForTimeout(500);
    const cls = await page.evaluate(() => {
      const entries: number[] = (window as any).__clsEntries ?? [];
      return entries.reduce((acc, v) => acc + v, 0);
    });
    expect(cls).toBe(0);
  });
});
