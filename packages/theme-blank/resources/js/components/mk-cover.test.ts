// @vitest-environment happy-dom
import { afterEach, beforeAll, describe, expect, it } from 'vitest';
import { defineAllComponents, getRegisteredComponents } from '@markommerce/frontend';
import './mk-cover';
import { MkCoverElement } from './mk-cover';
import { MkElement } from '@markommerce/frontend';

beforeAll(() => {
  if (!customElements.get('mk-cover')) {
    defineAllComponents();
  }
});

afterEach(() => {
  document.body.innerHTML = '';
});

describe('mk-cover', () => {
  it('mk-cover registers under the tag name "mk-cover"', () => {
    const registered = getRegisteredComponents().find((c) => c.tagName === 'mk-cover');
    expect(registered).toBeDefined();
    expect(registered!.base).toBe(MkCoverElement);
  });

  it('MkCoverElement extends MkElement', () => {
    expect(Object.getPrototypeOf(MkCoverElement)).toBe(MkElement);
  });

  it('the min-height attribute reflects between the minHeight property and the DOM attribute', async () => {
    const el = document.createElement('mk-cover') as MkCoverElement;
    document.body.appendChild(el);
    el.minHeight = '80vh';
    await el.updateComplete;
    expect(el.getAttribute('min-height')).toBe('80vh');
    el.removeAttribute('min-height');
    await el.updateComplete;
    expect(el.minHeight).toBeUndefined();
  });

  it('setting the minHeight property writes --mk-cover-min-height as an inline style on the element', async () => {
    const el = document.createElement('mk-cover') as MkCoverElement;
    document.body.appendChild(el);
    el.minHeight = '60vh';
    await el.updateComplete;
    expect(el.style.getPropertyValue('--mk-cover-min-height')).toBe('60vh');
  });

  it('clearing the minHeight property removes the --mk-cover-min-height inline style', async () => {
    const el = document.createElement('mk-cover') as MkCoverElement;
    document.body.appendChild(el);
    el.minHeight = '60vh';
    await el.updateComplete;
    el.minHeight = undefined;
    await el.updateComplete;
    expect(el.style.getPropertyValue('--mk-cover-min-height')).toBe('');
  });

  it('parsing <mk-cover min-height="80vh"></mk-cover> and upgrading sets style="--mk-cover-min-height: 80vh" synchronously during connectedCallback — assert immediately after defineAllComponents() returns, without awaiting updateComplete', () => {
    document.body.innerHTML = '<mk-cover min-height="80vh"></mk-cover>';
    const el = document.body.querySelector('mk-cover') as MkCoverElement;
    expect(el.style.getPropertyValue('--mk-cover-min-height')).toBe('80vh');
  });

  it('the documentation page exists at docs/src/content/docs/packages/theme-blank/mk-cover.md with the required sections', async () => {
    const fs = await import('node:fs');
    const path = await import('node:path');
    const { fileURLToPath } = await import('node:url');
    const __dirname = path.dirname(fileURLToPath(import.meta.url));
    const docPath = path.join(
      __dirname,
      '../../../../../docs/src/content/docs/packages/theme-blank/mk-cover.md',
    );
    expect(fs.existsSync(docPath), 'docs page should exist').toBe(true);
    const content = fs.readFileSync(docPath, 'utf-8');
    expect(content).toContain('title:');
    expect(content).toContain('description:');
    expect(content).toContain('mk-cover');
    expect(content).toContain('min-height');
    expect(content).toContain('--mk-cover-min-height');
    expect(content).toContain('mk-cover-header');
    expect(content).toContain('mk-cover-footer');
  });

  it('appending mk-cover to the DOM does not remove its light-DOM children', async () => {
    const el = document.createElement('mk-cover') as MkCoverElement;
    const header = document.createElement('div');
    header.className = 'mk-cover-header';
    header.textContent = 'header';
    const main = document.createElement('div');
    main.textContent = 'main content';
    const footer = document.createElement('div');
    footer.className = 'mk-cover-footer';
    footer.textContent = 'footer';
    el.appendChild(header);
    el.appendChild(main);
    el.appendChild(footer);
    document.body.appendChild(el);
    await el.updateComplete;

    expect(el.querySelector('.mk-cover-header')).not.toBeNull();
    expect(el.querySelector('.mk-cover-header')!.textContent).toBe('header');
    expect(el.querySelector('.mk-cover-footer')).not.toBeNull();
    expect(el.querySelector('.mk-cover-footer')!.textContent).toBe('footer');
    expect(el.children.length).toBe(3);
  });
});
