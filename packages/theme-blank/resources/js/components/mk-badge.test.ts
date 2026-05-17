// @vitest-environment happy-dom
import { afterEach, beforeAll, describe, expect, it } from 'vitest';
import { defineAllComponents, getRegisteredComponents } from '@markommerce/frontend';
import './mk-badge';
import { MkBadgeElement } from './mk-badge';
import { MkElement } from '@markommerce/frontend';

beforeAll(() => {
  if (!customElements.get('mk-badge')) {
    defineAllComponents();
  }
});

afterEach(() => {
  document.body.innerHTML = '';
});

describe('mk-badge', () => {
  it('mk-badge registers under the tag name "mk-badge"', () => {
    const registered = getRegisteredComponents().find((c) => c.tagName === 'mk-badge');
    expect(registered).toBeDefined();
    expect(registered!.base).toBe(MkBadgeElement);
  });

  it('MkBadgeElement extends MkElement', () => {
    expect(Object.getPrototypeOf(MkBadgeElement)).toBe(MkElement);
  });

  it('the variant attribute reflects between the property and the DOM attribute', async () => {
    const el = document.createElement('mk-badge') as MkBadgeElement;
    document.body.appendChild(el);
    await el.updateComplete;

    el.variant = 'success';
    await el.updateComplete;
    expect(el.getAttribute('variant')).toBe('success');

    el.removeAttribute('variant');
    await el.updateComplete;
    expect(el.variant).toBeUndefined();
  });

  it('the size attribute reflects between the property and the DOM attribute', async () => {
    const el = document.createElement('mk-badge') as MkBadgeElement;
    document.body.appendChild(el);
    await el.updateComplete;

    el.size = 'lg';
    await el.updateComplete;
    expect(el.getAttribute('size')).toBe('lg');

    el.removeAttribute('size');
    await el.updateComplete;
    expect(el.size).toBeUndefined();
  });

  it('appending mk-badge to the DOM does not remove its light-DOM text content', async () => {
    const el = document.createElement('mk-badge') as MkBadgeElement;
    el.textContent = 'Active';
    document.body.appendChild(el);
    await el.updateComplete;
    expect(el.textContent).toBe('Active');
  });

  it('the documentation page exists at docs/src/content/docs/packages/theme-blank/mk-badge.md with the required sections', async () => {
    const fs = await import('fs');
    const path = await import('path');
    const docPath = path.resolve(
      __dirname,
      '../../../../../docs/src/content/docs/packages/theme-blank/mk-badge.md',
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
