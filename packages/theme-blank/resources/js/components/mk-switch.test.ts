// @vitest-environment happy-dom
import { afterEach, beforeAll, describe, expect, it } from 'vitest';
import { defineAllComponents, getRegisteredComponents } from '@markommerce/frontend';
import './mk-switch';
import { MkSwitchElement } from './mk-switch';
import { MkElement } from '@markommerce/frontend';

beforeAll(() => {
  if (!customElements.get('mk-switch')) {
    defineAllComponents();
  }
});

afterEach(() => {
  document.body.innerHTML = '';
});

describe('mk-switch', () => {
  it('registers under the tag name "mk-switch"', () => {
    const registered = getRegisteredComponents().find((c) => c.tagName === 'mk-switch');
    expect(registered).toBeDefined();
    expect(registered!.base).toBe(MkSwitchElement);
  });

  it('MkSwitchElement extends MkElement', () => {
    expect(Object.getPrototypeOf(MkSwitchElement)).toBe(MkElement);
  });

  it('the size attribute reflects between property and the DOM attribute', async () => {
    const el = document.createElement('mk-switch') as MkSwitchElement;
    const input = document.createElement('input');
    input.type = 'checkbox';
    el.appendChild(input);
    document.body.appendChild(el);
    await el.updateComplete;

    el.size = 'lg';
    await el.updateComplete;
    expect(el.getAttribute('size')).toBe('lg');

    el.removeAttribute('size');
    await el.updateComplete;
    expect(el.size).toBeUndefined();
  });

  it('sets role="switch" on the inner input in connectedCallback', async () => {
    const el = document.createElement('mk-switch') as MkSwitchElement;
    const input = document.createElement('input');
    input.type = 'checkbox';
    el.appendChild(input);
    document.body.appendChild(el);
    await el.updateComplete;
    expect(input.getAttribute('role')).toBe('switch');
  });

  it('does not override an existing role attribute set by the server', async () => {
    const el = document.createElement('mk-switch') as MkSwitchElement;
    const input = document.createElement('input');
    input.type = 'checkbox';
    input.setAttribute('role', 'checkbox');
    el.appendChild(input);
    document.body.appendChild(el);
    await el.updateComplete;
    expect(input.getAttribute('role')).toBe('checkbox');
  });

  it('does not remove its light-DOM input child after upgrade', async () => {
    const el = document.createElement('mk-switch') as MkSwitchElement;
    const input = document.createElement('input');
    input.type = 'checkbox';
    el.appendChild(input);
    document.body.appendChild(el);
    await el.updateComplete;
    expect(el.querySelector('input[type="checkbox"]')).toBe(input);
  });

  it('the documentation page exists at docs/src/content/docs/packages/theme-blank/mk-switch.md with the required sections', async () => {
    const fs = await import('fs');
    const path = await import('path');
    const docPath = path.resolve(
      __dirname,
      '../../../../../docs/src/content/docs/packages/theme-blank/mk-switch.md',
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
