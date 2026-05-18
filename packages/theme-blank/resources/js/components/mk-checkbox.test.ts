// @vitest-environment happy-dom
import { afterEach, beforeAll, describe, expect, it } from 'vitest';
import { defineAllComponents, getRegisteredComponents } from '@markommerce/frontend';
import './mk-checkbox';
import { MkCheckboxElement } from './mk-checkbox';
import { MkElement } from '@markommerce/frontend';

beforeAll(() => {
  if (!customElements.get('mk-checkbox')) {
    defineAllComponents();
  }
});

afterEach(() => {
  document.body.innerHTML = '';
});

describe('mk-checkbox', () => {
  it('it registers under the tag name "mk-checkbox"', () => {
    const registered = getRegisteredComponents().find((c) => c.tagName === 'mk-checkbox');
    expect(registered).toBeDefined();
    expect(registered!.base).toBe(MkCheckboxElement);
  });

  it('it MkCheckboxElement extends MkElement', () => {
    expect(Object.getPrototypeOf(MkCheckboxElement)).toBe(MkElement);
  });

  it('it the size attribute reflects between property and the DOM attribute', async () => {
    const el = document.createElement('mk-checkbox') as MkCheckboxElement;
    const input = document.createElement('input');
    input.type = 'checkbox';
    el.appendChild(input);
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

  it('it does not remove its light-DOM input child after upgrade', async () => {
    const el = document.createElement('mk-checkbox') as MkCheckboxElement;
    const input = document.createElement('input');
    input.type = 'checkbox';
    el.appendChild(input);
    document.body.appendChild(el);
    await el.updateComplete;
    expect(el.querySelector('input[type="checkbox"]')).toBe(input);
  });

  it('it the documentation page exists at docs/src/content/docs/packages/theme-blank/mk-checkbox.md with the required sections', async () => {
    const fs = await import('fs');
    const path = await import('path');
    const docPath = path.resolve(
      __dirname,
      '../../../../../docs/src/content/docs/packages/theme-blank/mk-checkbox.md',
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
