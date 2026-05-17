// @vitest-environment happy-dom
import { afterEach, beforeAll, describe, expect, it } from 'vitest';
import { defineAllComponents, getRegisteredComponents } from '@markommerce/frontend';
import './mk-grid';
import { MkGridElement } from './mk-grid';
import { MkElement } from '@markommerce/frontend';

beforeAll(() => {
  if (!customElements.get('mk-grid')) {
    defineAllComponents();
  }
});

afterEach(() => {
  document.body.innerHTML = '';
});

describe('mk-grid', () => {
  it('mk-grid registers under the tag name "mk-grid"', () => {
    const registered = getRegisteredComponents().find((c) => c.tagName === 'mk-grid');
    expect(registered).toBeDefined();
    expect(registered!.base).toBe(MkGridElement);
  });

  it('MkGridElement extends MkElement', () => {
    expect(Object.getPrototypeOf(MkGridElement)).toBe(MkElement);
  });

  it('the min attribute reflects between the property and the DOM attribute', async () => {
    const el = document.createElement('mk-grid') as MkGridElement;
    document.body.appendChild(el);
    await el.updateComplete;

    el.min = '20rem';
    await el.updateComplete;
    expect(el.getAttribute('min')).toBe('20rem');

    el.removeAttribute('min');
    await el.updateComplete;
    expect(el.min).toBeFalsy();
  });

  it('setting the min property writes --mk-grid-min as an inline style on the element', async () => {
    const el = document.createElement('mk-grid') as MkGridElement;
    document.body.appendChild(el);
    await el.updateComplete;

    el.min = '20rem';
    await el.updateComplete;
    expect(el.style.getPropertyValue('--mk-grid-min')).toBe('20rem');
  });

  it('clearing the min property removes the --mk-grid-min inline style', async () => {
    const el = document.createElement('mk-grid') as MkGridElement;
    document.body.appendChild(el);
    await el.updateComplete;

    el.min = '20rem';
    await el.updateComplete;
    expect(el.style.getPropertyValue('--mk-grid-min')).toBe('20rem');

    el.min = undefined;
    await el.updateComplete;
    expect(el.style.getPropertyValue('--mk-grid-min')).toBe('');
  });

  it('parsing <mk-grid min="20rem"></mk-grid> via innerHTML and triggering upgrade (defineAllComponents) sets style="--mk-grid-min: 20rem" synchronously during connectedCallback — assert immediately after defineAllComponents() returns, without awaiting updateComplete', () => {
    document.body.innerHTML = '<mk-grid min="20rem"></mk-grid>';
    const el = document.body.querySelector('mk-grid') as MkGridElement;
    // connectedCallback should have set the style synchronously
    expect(el.style.getPropertyValue('--mk-grid-min')).toBe('20rem');
  });

  it('appending mk-grid to the DOM does not remove its light-DOM children', async () => {
    const el = document.createElement('mk-grid') as MkGridElement;
    el.innerHTML = '<div>child 1</div><div>child 2</div>';
    document.body.appendChild(el);
    await el.updateComplete;

    expect(el.querySelectorAll('div')).toHaveLength(2);
    expect(el.textContent).toContain('child 1');
    expect(el.textContent).toContain('child 2');
  });

  it('the documentation page exists at docs/src/content/docs/packages/theme-blank/mk-grid.md with the required sections', () => {
    const fs = require('node:fs') as typeof import('node:fs');
    const path = require('node:path') as typeof import('node:path');
    const docsPath = path.resolve(__dirname, '../../../../../docs/src/content/docs/packages/theme-blank/mk-grid.md');
    expect(fs.existsSync(docsPath), 'mk-grid.md should exist').toBe(true);

    const content = fs.readFileSync(docsPath, 'utf-8');
    // Frontmatter
    expect(content).toContain('title:');
    expect(content).toContain('description:');
    // Component API section
    expect(content).toContain('min');
    expect(content).toContain('gap');
    // CSS custom property
    expect(content).toContain('--mk-grid-min');
  });

  it('the gap attribute reflects between the property and the DOM attribute', async () => {
    const el = document.createElement('mk-grid') as MkGridElement;
    document.body.appendChild(el);
    await el.updateComplete;

    el.gap = '4';
    await el.updateComplete;
    expect(el.getAttribute('gap')).toBe('4');

    el.removeAttribute('gap');
    await el.updateComplete;
    expect(el.gap).toBeFalsy();
  });
});
