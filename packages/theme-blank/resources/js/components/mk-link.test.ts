// @vitest-environment happy-dom
import { afterEach, beforeAll, describe, expect, it } from 'vitest';
import { defineAllComponents, getRegisteredComponents } from '@markommerce/frontend';
import './mk-link';
import { MkLinkElement } from './mk-link';
import { MkElement } from '@markommerce/frontend';

beforeAll(() => {
  if (!customElements.get('mk-link')) {
    defineAllComponents();
  }
});

afterEach(() => {
  document.body.innerHTML = '';
});

describe('mk-link', () => {
  it('mk-link registers under the tag name "mk-link"', () => {
    const registered = getRegisteredComponents().find((c) => c.tagName === 'mk-link');
    expect(registered).toBeDefined();
    expect(registered!.base).toBe(MkLinkElement);
  });

  it('MkLinkElement extends MkElement', () => {
    expect(Object.getPrototypeOf(MkLinkElement)).toBe(MkElement);
  });

  it('the variant attribute reflects between the property and the DOM attribute', async () => {
    const el = document.createElement('mk-link') as MkLinkElement;
    document.body.appendChild(el);
    await el.updateComplete;

    el.variant = 'muted';
    await el.updateComplete;
    expect(el.getAttribute('variant')).toBe('muted');

    el.setAttribute('variant', 'danger');
    await el.updateComplete;
    expect(el.variant).toBe('danger');

    el.removeAttribute('variant');
    await el.updateComplete;
    expect(el.variant).toBeUndefined();
  });

  it('the underline attribute reflects between the property and the DOM attribute', async () => {
    const el = document.createElement('mk-link') as MkLinkElement;
    document.body.appendChild(el);
    await el.updateComplete;

    el.underline = 'always';
    await el.updateComplete;
    expect(el.getAttribute('underline')).toBe('always');

    el.setAttribute('underline', 'never');
    await el.updateComplete;
    expect(el.underline).toBe('never');

    el.removeAttribute('underline');
    await el.updateComplete;
    expect(el.underline).toBeUndefined();
  });

  it('appending mk-link to the DOM does not remove its light-DOM anchor child', async () => {
    const el = document.createElement('mk-link') as MkLinkElement;
    const anchor = document.createElement('a');
    anchor.href = '/about';
    anchor.textContent = 'About';
    el.appendChild(anchor);
    document.body.appendChild(el);
    await el.updateComplete;

    expect(el.querySelector('a')).not.toBeNull();
    expect(el.querySelector('a')!.textContent).toBe('About');
  });

  it('the documentation page exists at docs/src/content/docs/packages/theme-blank/mk-link.md with the required sections', async () => {
    const fs = await import('node:fs');
    const path = await import('node:path');
    const { fileURLToPath } = await import('node:url');
    const __dirname = path.dirname(fileURLToPath(import.meta.url));
    const docPath = path.join(
      __dirname,
      '../../../../../docs/src/content/docs/packages/theme-blank/mk-link.md',
    );
    expect(fs.existsSync(docPath), 'docs page should exist').toBe(true);
    const content = fs.readFileSync(docPath, 'utf-8');
    expect(content).toContain('title:');
    expect(content).toContain('description:');
    expect(content).toContain('mk-link');
    expect(content).toContain('variant');
    expect(content).toContain('underline');
  });
});
