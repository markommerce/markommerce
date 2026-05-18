// @vitest-environment happy-dom
import { afterEach, beforeAll, describe, expect, it } from 'vitest';
import { defineAllComponents, getRegisteredComponents } from '@markommerce/frontend';
import './mk-input';
import { MkInputElement } from './mk-input';
import { MkElement } from '@markommerce/frontend';

beforeAll(() => {
  if (!customElements.get('mk-input')) {
    defineAllComponents();
  }
});

afterEach(() => {
  document.body.innerHTML = '';
});

describe('mk-input', () => {
  it('registers under the tag name "mk-input"', () => {
    const registered = getRegisteredComponents().find((c) => c.tagName === 'mk-input');
    expect(registered).toBeDefined();
    expect(registered!.base).toBe(MkInputElement);
  });

  it('MkInputElement extends MkElement', () => {
    expect(Object.getPrototypeOf(MkInputElement)).toBe(MkElement);
  });

  it('the variant attribute reflects between property and the DOM attribute', async () => {
    const el = document.createElement('mk-input') as MkInputElement;
    document.body.appendChild(el);
    await el.updateComplete;

    el.variant = 'outline';
    await el.updateComplete;
    expect(el.getAttribute('variant')).toBe('outline');

    el.removeAttribute('variant');
    await el.updateComplete;
    expect(el.variant).toBeUndefined();
  });

  it('the size attribute reflects between property and the DOM attribute', async () => {
    const el = document.createElement('mk-input') as MkInputElement;
    document.body.appendChild(el);
    await el.updateComplete;

    el.size = 'lg';
    await el.updateComplete;
    expect(el.getAttribute('size')).toBe('lg');

    el.removeAttribute('size');
    await el.updateComplete;
    expect(el.size).toBeUndefined();
  });

  it('does not remove its light-DOM input child after upgrade', async () => {
    const el = document.createElement('mk-input') as MkInputElement;
    const input = document.createElement('input');
    input.type = 'text';
    el.appendChild(input);
    document.body.appendChild(el);
    await el.updateComplete;

    expect(el.querySelector('input')).not.toBeNull();
    expect(el.querySelector('input')!.type).toBe('text');
  });

  it('the documentation page exists at docs/src/content/docs/packages/theme-blank/mk-input.md with the required sections', async () => {
    const fs = await import('fs');
    const path = await import('path');
    const docPath = path.resolve(
      __dirname,
      '../../../../../docs/src/content/docs/packages/theme-blank/mk-input.md',
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
