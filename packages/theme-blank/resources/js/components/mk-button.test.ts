// @vitest-environment happy-dom
import { afterEach, beforeAll, describe, expect, it } from 'vitest';
import { defineAllComponents, getRegisteredComponents } from '@markommerce/frontend';
import './mk-button';
import { MkButtonElement } from './mk-button';
import { MkElement } from '@markommerce/frontend';

beforeAll(() => {
  if (!customElements.get('mk-button')) {
    defineAllComponents();
  }
});

afterEach(() => {
  document.body.innerHTML = '';
});

describe('mk-button', () => {
  it('it registers under the tag name "mk-button"', () => {
    const registered = getRegisteredComponents().find((c) => c.tagName === 'mk-button');
    expect(registered).toBeDefined();
    expect(registered!.base).toBe(MkButtonElement);
  });

  it('it MkButtonElement extends MkElement', () => {
    expect(Object.getPrototypeOf(MkButtonElement)).toBe(MkElement);
  });

  it('it the variant attribute reflects between property and the DOM attribute', async () => {
    const el = document.createElement('mk-button') as MkButtonElement;
    const btn = document.createElement('button');
    el.appendChild(btn);
    document.body.appendChild(el);
    await el.updateComplete;

    el.variant = 'primary';
    await el.updateComplete;
    expect(el.getAttribute('variant')).toBe('primary');

    el.variant = 'secondary';
    await el.updateComplete;
    expect(el.getAttribute('variant')).toBe('secondary');

    el.removeAttribute('variant');
    await el.updateComplete;
    expect(el.variant).toBeUndefined();
  });

  it('it the size attribute reflects between property and the DOM attribute', async () => {
    const el = document.createElement('mk-button') as MkButtonElement;
    const btn = document.createElement('button');
    el.appendChild(btn);
    document.body.appendChild(el);
    await el.updateComplete;

    el.size = 'lg';
    await el.updateComplete;
    expect(el.getAttribute('size')).toBe('lg');

    el.size = 'sm';
    await el.updateComplete;
    expect(el.getAttribute('size')).toBe('sm');

    el.removeAttribute('size');
    await el.updateComplete;
    expect(el.size).toBeUndefined();
  });

  it('it the loading attribute reflects between property and the DOM attribute', async () => {
    const el = document.createElement('mk-button') as MkButtonElement;
    const btn = document.createElement('button');
    el.appendChild(btn);
    document.body.appendChild(el);
    await el.updateComplete;

    el.loading = true;
    await el.updateComplete;
    expect(el.hasAttribute('loading')).toBe(true);

    el.loading = false;
    await el.updateComplete;
    expect(el.hasAttribute('loading')).toBe(false);
  });

  it('it sets disabled on the inner button element when loading is set to true', async () => {
    const el = document.createElement('mk-button') as MkButtonElement;
    const btn = document.createElement('button');
    el.appendChild(btn);
    document.body.appendChild(el);
    await el.updateComplete;

    el.loading = true;
    await el.updateComplete;
    expect(btn.disabled).toBe(true);
  });

  it('it sets aria-busy="true" on the mk-button wrapper when loading is set to true', async () => {
    const el = document.createElement('mk-button') as MkButtonElement;
    const btn = document.createElement('button');
    el.appendChild(btn);
    document.body.appendChild(el);
    await el.updateComplete;

    el.loading = true;
    await el.updateComplete;
    expect(el.getAttribute('aria-busy')).toBe('true');
  });

  it('it removes disabled from the inner button element when loading is set back to false', async () => {
    const el = document.createElement('mk-button') as MkButtonElement;
    const btn = document.createElement('button');
    el.appendChild(btn);
    document.body.appendChild(el);
    await el.updateComplete;

    el.loading = true;
    await el.updateComplete;
    el.loading = false;
    await el.updateComplete;
    expect(btn.disabled).toBe(false);
  });

  it('it removes aria-busy from the mk-button wrapper when loading is set back to false', async () => {
    const el = document.createElement('mk-button') as MkButtonElement;
    const btn = document.createElement('button');
    el.appendChild(btn);
    document.body.appendChild(el);
    await el.updateComplete;

    el.loading = true;
    await el.updateComplete;
    el.loading = false;
    await el.updateComplete;
    expect(el.hasAttribute('aria-busy')).toBe(false);
  });

  it('it does not remove its light-DOM button child after upgrade', async () => {
    const el = document.createElement('mk-button') as MkButtonElement;
    const btn = document.createElement('button');
    btn.textContent = 'Click me';
    el.appendChild(btn);
    document.body.appendChild(el);
    await el.updateComplete;
    expect(el.querySelector('button')).toBe(btn);
    expect(btn.textContent).toBe('Click me');
  });

  it('it the documentation page exists at docs/src/content/docs/packages/theme-blank/mk-button.md with the required sections', async () => {
    const fs = await import('fs');
    const path = await import('path');
    const docPath = path.resolve(
      __dirname,
      '../../../../../docs/src/content/docs/packages/theme-blank/mk-button.md',
    );
    expect(fs.existsSync(docPath)).toBe(true);
    const content = fs.readFileSync(docPath, 'utf-8');
    expect(content).toContain('title:');
    expect(content).toContain('description:');
    expect(content).toContain('## Installation');
    expect(content).toContain('## Usage');
    expect(content).toContain('## API Reference');
  });
});
