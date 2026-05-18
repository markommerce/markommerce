import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

const fixtureHtml = readFileSync(
  resolve(__dirname, 'fixtures/forms-page.html'),
  'utf-8',
);

test.describe('forms CLS', () => {
  test('all 10 form controls produce zero CLS in the unupgraded (CSS-only) state', async ({ page }) => {
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

  test('all 10 form controls produce zero CLS through the custom-element upgrade', async ({ page }) => {
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
      const tags = [
        'mk-button', 'mk-input', 'mk-textarea', 'mk-select',
        'mk-checkbox', 'mk-radio', 'mk-switch',
        'mk-field', 'mk-fieldset', 'mk-form',
      ];
      for (const tag of tags) {
        if (customElements.get(tag)) continue;
        customElements.define(tag, class extends HTMLElement {
          connectedCallback() {
            // Mirror Lit's ChildPart marker
            this.insertBefore(document.createComment(''), this.firstChild);

            if (tag === 'mk-field') {
              // Matches real mk-field.connectedCallback: sets initial data-state
              this.dataset['state'] = 'pristine';
            } else if (tag === 'mk-switch') {
              // Matches real mk-switch.connectedCallback: sets role="switch" on inner input
              const input = this.querySelector('input[type="checkbox"]');
              if (input && !input.hasAttribute('role')) {
                input.setAttribute('role', 'switch');
              }
            } else if (tag === 'mk-form') {
              // Matches real mk-form.connectedCallback: sets novalidate on inner form
              const form = this.querySelector('form');
              if (form) {
                form.setAttribute('novalidate', '');
              }
            }
            // mk-button: no loading side-effect required — the fixture does not set loading="true"
            // mk-input, mk-textarea, mk-select, mk-checkbox, mk-radio, mk-fieldset:
            //   no-op connectedCallback; they only register wrapper attributes CSS reads directly
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
