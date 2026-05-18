// @vitest-environment happy-dom
import { afterEach, beforeAll, describe, expect, it } from 'vitest';
import { defineAllComponents, getRegisteredComponents } from '@markommerce/frontend';
import './mk-heading';
import { MkHeadingElement } from './mk-heading';
import { MkElement } from '@markommerce/frontend';

beforeAll(() => {
  if (!customElements.get('mk-heading')) {
    defineAllComponents();
  }
});

afterEach(() => {
  document.body.innerHTML = '';
});

describe('mk-heading', () => {
  it('mk-heading registers under the tag name "mk-heading"', () => {
    const registered = getRegisteredComponents().find((c) => c.tagName === 'mk-heading');
    expect(registered).toBeDefined();
    expect(registered!.base).toBe(MkHeadingElement);
  });

  it('MkHeadingElement extends MkElement', () => {
    expect(Object.getPrototypeOf(MkHeadingElement)).toBe(MkElement);
  });

  it('the level attribute reflects between the property and the DOM attribute', async () => {
    const el = document.createElement('mk-heading') as MkHeadingElement;
    document.body.appendChild(el);
    await el.updateComplete;

    el.level = '3';
    await el.updateComplete;
    expect(el.getAttribute('level')).toBe('3');

    el.removeAttribute('level');
    await el.updateComplete;
    expect(el.level).toBeNull();
  });

  it('the size attribute reflects between the property and the DOM attribute', async () => {
    const el = document.createElement('mk-heading') as MkHeadingElement;
    document.body.appendChild(el);
    await el.updateComplete;

    el.size = 'lg';
    await el.updateComplete;
    expect(el.getAttribute('size')).toBe('lg');

    el.removeAttribute('size');
    await el.updateComplete;
    expect(el.size).toBeNull();
  });

  it('the weight attribute reflects between the property and the DOM attribute', async () => {
    const el = document.createElement('mk-heading') as MkHeadingElement;
    document.body.appendChild(el);
    await el.updateComplete;

    el.weight = 'medium';
    await el.updateComplete;
    expect(el.getAttribute('weight')).toBe('medium');

    el.removeAttribute('weight');
    await el.updateComplete;
    expect(el.weight).toBeNull();
  });

  it('parsing <mk-heading level="2">Hello</mk-heading> and upgrading sets role="heading" and aria-level="2" synchronously during connectedCallback — assert immediately after defineAllComponents() returns, without awaiting updateComplete', () => {
    document.body.innerHTML = '<mk-heading level="2">Hello</mk-heading>';
    const el = document.body.querySelector('mk-heading') as MkHeadingElement;
    expect(el.getAttribute('role')).toBe('heading');
    expect(el.getAttribute('aria-level')).toBe('2');
  });

  it('parsing <mk-heading><h2>Hello</h2></mk-heading> does NOT set role or aria-level on the host (defers to the inner heading element)', async () => {
    const el = document.createElement('mk-heading') as MkHeadingElement;
    const h2 = document.createElement('h2');
    h2.textContent = 'Hello';
    el.appendChild(h2);
    document.body.appendChild(el);
    await el.updateComplete;
    expect(el.getAttribute('role')).toBeNull();
    expect(el.getAttribute('aria-level')).toBeNull();
  });

  it('the component does not override an existing role attribute set by the consumer', () => {
    document.body.innerHTML = '<mk-heading level="2" role="presentation">Hello</mk-heading>';
    const el = document.body.querySelector('mk-heading') as MkHeadingElement;
    expect(el.getAttribute('role')).toBe('presentation');
  });

  it('the component does not override an existing aria-level attribute set by the consumer', () => {
    document.body.innerHTML = '<mk-heading level="2" aria-level="3">Hello</mk-heading>';
    const el = document.body.querySelector('mk-heading') as MkHeadingElement;
    expect(el.getAttribute('aria-level')).toBe('3');
  });

  it('appending mk-heading to the DOM does not remove its light-DOM text content', async () => {
    const el = document.createElement('mk-heading') as MkHeadingElement;
    el.textContent = 'About us';
    document.body.appendChild(el);
    await el.updateComplete;
    expect(el.textContent).toBe('About us');
  });

  it('the documentation page exists at docs/src/content/docs/packages/theme-blank/mk-heading.md with the required sections', async () => {
    const fs = await import('fs');
    const path = await import('path');
    const docPath = path.resolve(
      __dirname,
      '../../../../../docs/src/content/docs/packages/theme-blank/mk-heading.md',
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
