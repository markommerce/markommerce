// @vitest-environment happy-dom
import { afterEach, beforeAll, describe, expect, it } from 'vitest';
import { defineAllComponents, getRegisteredComponents } from '@markommerce/frontend';
import './mk-switcher';
import { MkSwitcherElement } from './mk-switcher';
import { MkElement } from '@markommerce/frontend';

beforeAll(() => {
  if (!customElements.get('mk-switcher')) {
    defineAllComponents();
  }
});

afterEach(() => {
  document.body.innerHTML = '';
});

describe('mk-switcher', () => {
  it('mk-switcher registers under the tag name "mk-switcher"', () => {
    const registered = getRegisteredComponents().find((c) => c.tagName === 'mk-switcher');
    expect(registered).toBeDefined();
    expect(registered!.base).toBe(MkSwitcherElement);
  });

  it('MkSwitcherElement extends MkElement', () => {
    expect(Object.getPrototypeOf(MkSwitcherElement)).toBe(MkElement);
  });

  it('the threshold attribute reflects between the property and the DOM attribute', async () => {
    const el = document.createElement('mk-switcher') as MkSwitcherElement;
    document.body.appendChild(el);
    await el.updateComplete;

    el.threshold = '20rem';
    await el.updateComplete;
    expect(el.getAttribute('threshold')).toBe('20rem');

    el.removeAttribute('threshold');
    await el.updateComplete;
    expect(el.threshold).toBeUndefined();
  });

  it('the gap attribute reflects between the property and the DOM attribute', async () => {
    const el = document.createElement('mk-switcher') as MkSwitcherElement;
    document.body.appendChild(el);
    await el.updateComplete;

    el.gap = '5';
    await el.updateComplete;
    expect(el.getAttribute('gap')).toBe('5');

    el.removeAttribute('gap');
    await el.updateComplete;
    expect(el.gap).toBeUndefined();
  });

  it('the limit attribute reflects between the property and the DOM attribute', async () => {
    const el = document.createElement('mk-switcher') as MkSwitcherElement;
    document.body.appendChild(el);
    await el.updateComplete;

    el.limit = 3;
    await el.updateComplete;
    expect(el.getAttribute('limit')).toBe('3');

    el.removeAttribute('limit');
    await el.updateComplete;
    expect(el.limit).toBeUndefined();
  });

  it('setting the threshold property writes --mk-switcher-threshold as an inline style on the element', async () => {
    const el = document.createElement('mk-switcher') as MkSwitcherElement;
    document.body.appendChild(el);
    await el.updateComplete;

    el.threshold = '20rem';
    await el.updateComplete;
    expect(el.style.getPropertyValue('--mk-switcher-threshold')).toBe('20rem');
  });

  it('clearing the threshold property removes the --mk-switcher-threshold inline style', async () => {
    const el = document.createElement('mk-switcher') as MkSwitcherElement;
    document.body.appendChild(el);
    el.threshold = '20rem';
    await el.updateComplete;

    el.threshold = undefined;
    await el.updateComplete;
    expect(el.style.getPropertyValue('--mk-switcher-threshold')).toBe('');
  });

  it('parsing <mk-switcher threshold="20rem"></mk-switcher> and upgrading sets style="--mk-switcher-threshold: 20rem" synchronously during connectedCallback — assert immediately after defineAllComponents() returns, without awaiting updateComplete', () => {
    document.body.innerHTML = '<mk-switcher threshold="20rem"></mk-switcher>';
    const el = document.body.querySelector('mk-switcher') as MkSwitcherElement;
    expect(el.style.getPropertyValue('--mk-switcher-threshold')).toBe('20rem');
  });

  it('appending mk-switcher to the DOM does not remove its light-DOM children', async () => {
    const el = document.createElement('mk-switcher') as MkSwitcherElement;
    const child = document.createElement('div');
    child.textContent = 'switcher content';
    el.appendChild(child);
    document.body.appendChild(el);
    await el.updateComplete;

    expect(el.querySelector('div')).not.toBeNull();
    expect(el.querySelector('div')!.textContent).toBe('switcher content');
  });

  it('the documentation page exists at docs/src/content/docs/packages/theme-blank/mk-switcher.md with the required sections', async () => {
    const fs = await import('node:fs');
    const path = await import('node:path');
    const { fileURLToPath } = await import('node:url');
    const __dirname = path.dirname(fileURLToPath(import.meta.url));
    const docPath = path.join(
      __dirname,
      '../../../../../docs/src/content/docs/packages/theme-blank/mk-switcher.md',
    );
    expect(fs.existsSync(docPath), 'docs page should exist').toBe(true);
    const content = fs.readFileSync(docPath, 'utf-8');
    expect(content).toContain('title:');
    expect(content).toContain('description:');
    expect(content).toContain('mk-switcher');
    expect(content).toContain('threshold');
    expect(content).toContain('gap');
    expect(content).toContain('limit');
    expect(content).toContain('--mk-switcher-threshold');
  });
});
