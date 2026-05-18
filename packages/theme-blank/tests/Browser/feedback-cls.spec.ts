import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

const fixtureHtml = readFileSync(
  resolve(__dirname, 'fixtures/feedback-page.html'),
  'utf-8',
);

test.describe('feedback CLS', () => {
  test('pre-upgrade CLS is zero for the feedback fixture', async ({ page }) => {
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

  test('post-upgrade CLS is zero for the feedback fixture', async ({ page }) => {
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
        'mk-alert', 'mk-toast', 'mk-spinner', 'mk-skeleton', 'mk-modal', 'mk-drawer',
      ];
      for (const tag of tags) {
        if (customElements.get(tag)) continue;
        customElements.define(tag, class extends HTMLElement {
          connectedCallback() {
            // Mirror Lit's ChildPart marker
            this.insertBefore(document.createComment(''), this.firstChild);

            if (tag === 'mk-spinner') {
              // Sets role="status" and aria-live="polite" on the spinner
              if (!this.hasAttribute('role')) {
                this.setAttribute('role', 'status');
              }
              if (!this.hasAttribute('aria-live')) {
                this.setAttribute('aria-live', 'polite');
              }
              // Adds visually hidden loading text if none present
              const hasVisuallyHidden = this.querySelector('span.mk-visually-hidden') !== null;
              const hasTextContent = Array.from(this.childNodes).some(
                (node) => node.nodeType === Node.TEXT_NODE && node.textContent?.trim(),
              );
              if (!hasTextContent && !hasVisuallyHidden) {
                const span = document.createElement('span');
                span.className = 'mk-visually-hidden';
                span.textContent = 'Loading…';
                this.appendChild(span);
              }
            } else if (tag === 'mk-skeleton') {
              // Sets aria-hidden="true"
              if (!this.hasAttribute('aria-hidden')) {
                this.setAttribute('aria-hidden', 'true');
              }
            } else if (tag === 'mk-toast') {
              // Sets role="status" and tabindex="0"
              if (!this.hasAttribute('role')) {
                this.setAttribute('role', 'status');
              }
              if (!this.hasAttribute('tabindex')) {
                this.setAttribute('tabindex', '0');
              }
            } else if (tag === 'mk-drawer') {
              // Sets default placement="right" if not present
              if (!this.hasAttribute('placement')) {
                this.setAttribute('placement', 'right');
              }
            }
            // mk-alert: dismissible button is only added when dismissible attr is set (not in fixture)
            // mk-modal: only adds event listeners, no layout side effects
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

  test('the fixture renders 4 mk-alert variants (info, success, warning, danger)', async ({ page }) => {
    await page.setContent(fixtureHtml, { waitUntil: 'networkidle' });
    await expect(page.locator('mk-alert[variant="info"]')).toHaveCount(1);
    await expect(page.locator('mk-alert[variant="success"]')).toHaveCount(1);
    await expect(page.locator('mk-alert[variant="warning"]')).toHaveCount(1);
    await expect(page.locator('mk-alert[variant="danger"]')).toHaveCount(1);
  });

  test('the fixture renders 3 mk-toast elements pre-rendered inside an <ol class="mk-toast-region" role="region" aria-live="polite" aria-label="Notifications"> with each <mk-toast> wrapped in its own <li>', async ({ page }) => {
    await page.setContent(fixtureHtml, { waitUntil: 'networkidle' });
    const region = page.locator('ol.mk-toast-region[role="region"][aria-live="polite"][aria-label="Notifications"]');
    await expect(region).toHaveCount(1);
    const listItems = region.locator('> li');
    await expect(listItems).toHaveCount(3);
    const toasts = region.locator('li > mk-toast');
    await expect(toasts).toHaveCount(3);
  });

  test('the fixture renders 3 mk-spinner sizes (sm, base, lg)', async ({ page }) => {
    await page.setContent(fixtureHtml, { waitUntil: 'networkidle' });
    await expect(page.locator('mk-spinner[size="sm"]')).toHaveCount(1);
    await expect(page.locator('mk-spinner[size="base"]')).toHaveCount(1);
    await expect(page.locator('mk-spinner[size="lg"]')).toHaveCount(1);
  });

  test('the fixture renders 3 mk-skeleton variants (text, circle, rect)', async ({ page }) => {
    await page.setContent(fixtureHtml, { waitUntil: 'networkidle' });
    await expect(page.locator('mk-skeleton[variant="text"]')).toHaveCount(1);
    await expect(page.locator('mk-skeleton[variant="circle"]')).toHaveCount(1);
    await expect(page.locator('mk-skeleton[variant="rect"]')).toHaveCount(1);
  });

  test('the fixture renders a closed mk-modal with the form <mk-modal><dialog>…content…</dialog></mk-modal> (no open attribute, dialog is display:none by default)', async ({ page }) => {
    await page.setContent(fixtureHtml, { waitUntil: 'networkidle' });
    const modal = page.locator('mk-modal');
    await expect(modal).toHaveCount(1);
    await expect(modal).not.toHaveAttribute('open');
    const dialog = modal.locator('> dialog');
    await expect(dialog).toHaveCount(1);
  });

  test('the fixture renders a closed mk-drawer with placement="right" and the form <mk-drawer placement="right"><dialog>…content…</dialog></mk-drawer>', async ({ page }) => {
    await page.setContent(fixtureHtml, { waitUntil: 'networkidle' });
    const drawer = page.locator('mk-drawer[placement="right"]');
    await expect(drawer).toHaveCount(1);
    await expect(drawer).not.toHaveAttribute('open');
    const dialog = drawer.locator('> dialog');
    await expect(dialog).toHaveCount(1);
  });
});
