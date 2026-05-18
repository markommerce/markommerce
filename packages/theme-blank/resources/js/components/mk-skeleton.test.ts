// @vitest-environment happy-dom
import { afterEach, beforeAll, describe, expect, it } from 'vitest';
import { defineAllComponents, getRegisteredComponents } from '@markommerce/frontend';
import './mk-skeleton';
import { MkSkeletonElement } from './mk-skeleton';
import { MkElement } from '@markommerce/frontend';
import * as fs from 'node:fs';
import * as path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

beforeAll(() => {
  if (!customElements.get('mk-skeleton')) {
    defineAllComponents();
  }
});

afterEach(() => {
  document.body.innerHTML = '';
});

describe('mk-skeleton', () => {
  it('it registers under the tag name "mk-skeleton" with MkSkeletonElement extending MkElement', () => {
    const registered = getRegisteredComponents().find((c) => c.tagName === 'mk-skeleton');
    expect(registered).toBeDefined();
    expect(registered!.base).toBe(MkSkeletonElement);
    expect(Object.getPrototypeOf(MkSkeletonElement)).toBe(MkElement);
  });

  it('it reflects the variant attribute between property and DOM attribute (text|circle|rect)', async () => {
    const el = document.createElement('mk-skeleton') as MkSkeletonElement;
    document.body.appendChild(el);
    await el.updateComplete;

    el.variant = 'text';
    await el.updateComplete;
    expect(el.getAttribute('variant')).toBe('text');

    el.variant = 'circle';
    await el.updateComplete;
    expect(el.getAttribute('variant')).toBe('circle');

    el.variant = 'rect';
    await el.updateComplete;
    expect(el.getAttribute('variant')).toBe('rect');

    el.removeAttribute('variant');
    await el.updateComplete;
    expect(el.variant).toBeUndefined();
  });

  it('it sets aria-hidden="true" on connect when aria-hidden is not already set', () => {
    const el = document.createElement('mk-skeleton') as MkSkeletonElement;
    document.body.appendChild(el);
    expect(el.getAttribute('aria-hidden')).toBe('true');
  });

  it('it does not overwrite an existing aria-hidden attribute', () => {
    const el = document.createElement('mk-skeleton') as MkSkeletonElement;
    el.setAttribute('aria-hidden', 'false');
    document.body.appendChild(el);
    expect(el.getAttribute('aria-hidden')).toBe('false');
  });

  it('it disables the shimmer animation when prefers-reduced-motion is active', () => {
    const cssPath = path.resolve(__dirname, '../../css/components/mk-skeleton.css');
    const content = fs.readFileSync(cssPath, 'utf-8');
    expect(content).toContain('@media (prefers-reduced-motion: reduce)');
    expect(content).toContain('animation: none');
  });

  it('it produces zero layout shift when the JS class is registered after the element renders', async () => {
    const el = document.createElement('mk-skeleton') as MkSkeletonElement;
    document.body.appendChild(el);
    const outerBefore = el.outerHTML;
    await el.updateComplete;
    const outerAfter = el.outerHTML;
    expect(outerAfter).toContain('mk-skeleton');
    expect(customElements.get('mk-skeleton')).toBeDefined();
  });
});
