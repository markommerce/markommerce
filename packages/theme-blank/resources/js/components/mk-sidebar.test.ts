// @vitest-environment happy-dom
import { afterEach, beforeAll, describe, expect, it } from 'vitest';
import { defineAllComponents, getRegisteredComponents } from '@markommerce/frontend';
import './mk-sidebar';
import { MkSidebarElement } from './mk-sidebar';
import { MkElement } from '@markommerce/frontend';

beforeAll(() => {
  if (!customElements.get('mk-sidebar')) {
    defineAllComponents();
  }
});

afterEach(() => {
  document.body.innerHTML = '';
});

describe('mk-sidebar', () => {
  it('mk-sidebar registers under the tag name "mk-sidebar"', () => {
    const registered = getRegisteredComponents().find((c) => c.tagName === 'mk-sidebar');
    expect(registered).toBeDefined();
    expect(registered!.base).toBe(MkSidebarElement);
  });

  it('MkSidebarElement extends MkElement', () => {
    expect(Object.getPrototypeOf(MkSidebarElement)).toBe(MkElement);
  });

  it('the side attribute reflects between the property and the DOM attribute', async () => {
    const el = document.createElement('mk-sidebar') as MkSidebarElement;
    document.body.appendChild(el);
    await el.updateComplete;

    el.side = 'right';
    await el.updateComplete;
    expect(el.getAttribute('side')).toBe('right');

    el.removeAttribute('side');
    await el.updateComplete;
    expect(el.side).toBeUndefined();
  });

  it('the width attribute reflects between the property and the DOM attribute', async () => {
    const el = document.createElement('mk-sidebar') as MkSidebarElement;
    document.body.appendChild(el);
    await el.updateComplete;

    el.width = '20rem';
    await el.updateComplete;
    expect(el.getAttribute('width')).toBe('20rem');

    el.removeAttribute('width');
    await el.updateComplete;
    expect(el.width).toBeUndefined();
  });

  it('setting the width property writes --mk-sidebar-width as an inline style on the element', async () => {
    const el = document.createElement('mk-sidebar') as MkSidebarElement;
    document.body.appendChild(el);
    await el.updateComplete;

    el.width = '20rem';
    await el.updateComplete;
    expect(el.style.getPropertyValue('--mk-sidebar-width')).toBe('20rem');
  });

  it('clearing the width property removes the --mk-sidebar-width inline style', async () => {
    const el = document.createElement('mk-sidebar') as MkSidebarElement;
    document.body.appendChild(el);
    el.width = '20rem';
    await el.updateComplete;

    el.width = undefined;
    await el.updateComplete;
    expect(el.style.getPropertyValue('--mk-sidebar-width')).toBe('');
  });

  it('parsing <mk-sidebar width="14rem"></mk-sidebar> and upgrading sets style="--mk-sidebar-width: 14rem" synchronously during connectedCallback — assert immediately after defineAllComponents() returns, without awaiting updateComplete', () => {
    document.body.innerHTML = '<mk-sidebar width="14rem"></mk-sidebar>';
    const el = document.body.querySelector('mk-sidebar') as MkSidebarElement;
    expect(el.style.getPropertyValue('--mk-sidebar-width')).toBe('14rem');
  });

  it('appending mk-sidebar to the DOM does not remove its light-DOM children', async () => {
    const el = document.createElement('mk-sidebar') as MkSidebarElement;
    const child = document.createElement('div');
    child.textContent = 'sidebar content';
    el.appendChild(child);
    document.body.appendChild(el);
    await el.updateComplete;

    expect(el.querySelector('div')).not.toBeNull();
    expect(el.querySelector('div')!.textContent).toBe('sidebar content');
  });

  it('the documentation page exists at docs/src/content/docs/packages/theme-blank/mk-sidebar.md with the required sections', async () => {
    const fs = await import('node:fs');
    const path = await import('node:path');
    const { fileURLToPath } = await import('node:url');
    const __dirname = path.dirname(fileURLToPath(import.meta.url));
    const docPath = path.join(
      __dirname,
      '../../../../../docs/src/content/docs/packages/theme-blank/mk-sidebar.md',
    );
    expect(fs.existsSync(docPath), 'docs page should exist').toBe(true);
    const content = fs.readFileSync(docPath, 'utf-8');
    expect(content).toContain('title:');
    expect(content).toContain('description:');
    expect(content).toContain('mk-sidebar');
    expect(content).toContain('side');
    expect(content).toContain('width');
    expect(content).toContain('--mk-sidebar-width');
  });
});
