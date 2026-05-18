// @vitest-environment happy-dom
import { afterEach, beforeAll, describe, expect, it } from 'vitest';
import { defineAllComponents, getRegisteredComponents } from '@markommerce/frontend';
import './mk-select';
import { MkSelectElement } from './mk-select';
import { MkElement } from '@markommerce/frontend';

beforeAll(() => {
  if (!customElements.get('mk-select')) {
    defineAllComponents();
  }
});

afterEach(() => {
  document.body.innerHTML = '';
});

describe('mk-select', () => {
  it('it registers under the tag name "mk-select"', () => {
    const registered = getRegisteredComponents().find((c) => c.tagName === 'mk-select');
    expect(registered).toBeDefined();
    expect(registered!.base).toBe(MkSelectElement);
  });

  it('it MkSelectElement extends MkElement', () => {
    expect(Object.getPrototypeOf(MkSelectElement)).toBe(MkElement);
  });

  it('it the variant attribute reflects between property and the DOM attribute', async () => {
    const el = document.createElement('mk-select') as MkSelectElement;
    document.body.appendChild(el);
    await el.updateComplete;

    el.variant = 'outline';
    await el.updateComplete;
    expect(el.getAttribute('variant')).toBe('outline');

    el.variant = 'filled';
    await el.updateComplete;
    expect(el.getAttribute('variant')).toBe('filled');

    el.removeAttribute('variant');
    await el.updateComplete;
    expect(el.variant).toBeUndefined();
  });

  it('it the size attribute reflects between property and the DOM attribute', async () => {
    const el = document.createElement('mk-select') as MkSelectElement;
    document.body.appendChild(el);
    await el.updateComplete;

    el.size = 'sm';
    await el.updateComplete;
    expect(el.getAttribute('size')).toBe('sm');

    el.size = 'lg';
    await el.updateComplete;
    expect(el.getAttribute('size')).toBe('lg');

    el.removeAttribute('size');
    await el.updateComplete;
    expect(el.size).toBeUndefined();
  });

  it('it does not remove its light-DOM select child after upgrade', async () => {
    const el = document.createElement('mk-select') as MkSelectElement;
    const select = document.createElement('select');
    const option = document.createElement('option');
    option.value = 'xs';
    option.textContent = 'XS';
    select.appendChild(option);
    el.appendChild(select);
    document.body.appendChild(el);
    await el.updateComplete;

    expect(el.querySelector('select')).not.toBeNull();
    expect(el.querySelector('option')).not.toBeNull();
    expect(el.querySelector('option')!.textContent).toBe('XS');
  });

  it('it the documentation page exists at docs/src/content/docs/packages/theme-blank/mk-select.md with the required sections', async () => {
    const fs = await import('node:fs');
    const path = await import('node:path');
    const { fileURLToPath } = await import('node:url');
    const __dirname = path.dirname(fileURLToPath(import.meta.url));
    const docPath = path.join(
      __dirname,
      '../../../../../docs/src/content/docs/packages/theme-blank/mk-select.md',
    );
    expect(fs.existsSync(docPath), 'docs page should exist').toBe(true);
    const content = fs.readFileSync(docPath, 'utf-8');
    expect(content).toContain('title:');
    expect(content).toContain('description:');
    expect(content).toContain('## Installation');
    expect(content).toContain('## Usage');
    expect(content).toContain('## API Reference');
  });
});
