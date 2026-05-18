// @vitest-environment happy-dom
import { afterEach, beforeAll, describe, expect, it } from 'vitest';
import { defineAllComponents, getRegisteredComponents } from '@markommerce/frontend';
import './mk-container';
import { MkContainerElement } from './mk-container';
import { MkElement } from '@markommerce/frontend';

beforeAll(() => {
  if (!customElements.get('mk-container')) {
    defineAllComponents();
  }
});

afterEach(() => {
  document.body.innerHTML = '';
});

describe('mk-container', () => {
  it('mk-container registers under the tag name "mk-container"', () => {
    const registered = getRegisteredComponents().find((c) => c.tagName === 'mk-container');
    expect(registered).toBeDefined();
    expect(registered!.base).toBe(MkContainerElement);
  });

  it('MkContainerElement extends MkElement', () => {
    expect(Object.getPrototypeOf(MkContainerElement)).toBe(MkElement);
  });

  it('the size attribute reflects between the property and the DOM attribute', async () => {
    const el = document.createElement('mk-container') as MkContainerElement;
    document.body.appendChild(el);
    await el.updateComplete;

    el.size = 'sm';
    await el.updateComplete;
    expect(el.getAttribute('size')).toBe('sm');

    el.setAttribute('size', 'xl');
    await el.updateComplete;
    expect(el.size).toBe('xl');

    el.removeAttribute('size');
    await el.updateComplete;
    expect(el.size).toBeUndefined();
  });

  it('appending mk-container to the DOM does not remove its light-DOM children', async () => {
    const el = document.createElement('mk-container') as MkContainerElement;
    const child = document.createElement('div');
    child.textContent = 'container content';
    el.appendChild(child);
    document.body.appendChild(el);
    await el.updateComplete;

    expect(el.querySelector('div')).not.toBeNull();
    expect(el.querySelector('div')!.textContent).toBe('container content');
  });

  it('the documentation page exists at docs/src/content/docs/packages/theme-blank/mk-container.md with the required sections', async () => {
    const fs = await import('node:fs');
    const path = await import('node:path');
    const { fileURLToPath } = await import('node:url');
    const __dirname = path.dirname(fileURLToPath(import.meta.url));
    const docPath = path.join(
      __dirname,
      '../../../../../docs/src/content/docs/packages/theme-blank/mk-container.md',
    );
    expect(fs.existsSync(docPath), 'docs page should exist').toBe(true);
    const content = fs.readFileSync(docPath, 'utf-8');
    expect(content).toContain('title:');
    expect(content).toContain('description:');
    expect(content).toContain('mk-container');
    expect(content).toContain('size');
  });
});
