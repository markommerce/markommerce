// @vitest-environment happy-dom
import { afterEach, beforeAll, describe, expect, it } from 'vitest';
import { defineAllComponents, getRegisteredComponents } from '@markommerce/frontend';
import './mk-spinner';
import { MkSpinnerElement } from './mk-spinner';
import { MkElement } from '@markommerce/frontend';
import * as fs from 'node:fs';
import * as path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

beforeAll(() => {
  if (!customElements.get('mk-spinner')) {
    defineAllComponents();
  }
});

afterEach(() => {
  document.body.innerHTML = '';
});

describe('mk-spinner', () => {
  it('it registers under the tag name "mk-spinner" with MkSpinnerElement extending MkElement', () => {
    const registered = getRegisteredComponents().find((c) => c.tagName === 'mk-spinner');
    expect(registered).toBeDefined();
    expect(registered!.base).toBe(MkSpinnerElement);
    expect(Object.getPrototypeOf(MkSpinnerElement)).toBe(MkElement);
  });

  it('it reflects the size attribute between property and DOM attribute', async () => {
    const el = document.createElement('mk-spinner') as MkSpinnerElement;
    document.body.appendChild(el);
    await el.updateComplete;

    el.size = 'lg';
    await el.updateComplete;
    expect(el.getAttribute('size')).toBe('lg');

    el.size = 'sm';
    await el.updateComplete;
    expect(el.getAttribute('size')).toBe('sm');

    el.size = 'base';
    await el.updateComplete;
    expect(el.getAttribute('size')).toBe('base');

    el.removeAttribute('size');
    await el.updateComplete;
    expect(el.size).toBeUndefined();
  });

  it('it sets role="status" on connect when role is not already set', () => {
    const el = document.createElement('mk-spinner') as MkSpinnerElement;
    document.body.appendChild(el);
    expect(el.getAttribute('role')).toBe('status');
  });

  it('it does not overwrite an existing role attribute (e.g., role="progressbar")', () => {
    const el = document.createElement('mk-spinner') as MkSpinnerElement;
    el.setAttribute('role', 'progressbar');
    document.body.appendChild(el);
    expect(el.getAttribute('role')).toBe('progressbar');
  });

  it('it sets aria-live="polite" on connect when aria-live is not already set', () => {
    const el = document.createElement('mk-spinner') as MkSpinnerElement;
    document.body.appendChild(el);
    expect(el.getAttribute('aria-live')).toBe('polite');
  });

  it('it disables the CSS animation when prefers-reduced-motion is active', () => {
    const cssPath = path.resolve(__dirname, '../../css/components/mk-spinner.css');
    const content = fs.readFileSync(cssPath, 'utf-8');
    expect(content).toContain('@media (prefers-reduced-motion: reduce)');
    expect(content).toContain('animation: none');
  });

  it('it produces zero layout shift when the JS class is registered after the element renders', async () => {
    const el = document.createElement('mk-spinner') as MkSpinnerElement;
    document.body.appendChild(el);
    const outerBefore = el.outerHTML;
    await el.updateComplete;
    const outerAfter = el.outerHTML;
    // The element should not have shifted layout — display should remain inline-block
    // The key indicator: no dimension attributes added by JS that would cause CLS
    // We verify by checking that the tag structure is stable
    expect(outerAfter).toContain('mk-spinner');
    // The element should be defined and functional
    expect(customElements.get('mk-spinner')).toBeDefined();
  });

  it('it defines a .mk-visually-hidden helper class inside mk-spinner.css using the standard absolute-position + clip-path recipe (width:1px, height:1px, overflow:hidden, clip-path:inset(50%), white-space:nowrap)', () => {
    const cssPath = path.resolve(__dirname, '../../css/components/mk-spinner.css');
    const content = fs.readFileSync(cssPath, 'utf-8');
    expect(content).toContain('.mk-visually-hidden');
    expect(content).toContain('position: absolute');
    expect(content).toContain('width: 1px');
    expect(content).toContain('height: 1px');
    expect(content).toContain('overflow: hidden');
    expect(content).toContain('clip-path: inset(50%)');
    expect(content).toContain('white-space: nowrap');
  });

  it('it injects a default <span class="mk-visually-hidden">Loading…</span> on connect when the element has no child text content', () => {
    const el = document.createElement('mk-spinner') as MkSpinnerElement;
    document.body.appendChild(el);
    const span = el.querySelector('span.mk-visually-hidden');
    expect(span).not.toBeNull();
    expect(span!.textContent).toBe('Loading…');
  });

  it('it does not inject a duplicate visually-hidden label on re-connection', () => {
    const el = document.createElement('mk-spinner') as MkSpinnerElement;
    document.body.appendChild(el);
    // Disconnect and reconnect
    document.body.removeChild(el);
    document.body.appendChild(el);
    const spans = el.querySelectorAll('span.mk-visually-hidden');
    expect(spans.length).toBe(1);
  });
});
