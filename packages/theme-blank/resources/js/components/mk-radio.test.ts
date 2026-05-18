// @vitest-environment happy-dom
import { afterEach, beforeAll, describe, expect, it } from 'vitest';
import { defineAllComponents, getRegisteredComponents } from '@markommerce/frontend';
import './mk-radio';
import { MkRadioElement } from './mk-radio';
import { MkElement } from '@markommerce/frontend';

beforeAll(() => {
  if (!customElements.get('mk-radio')) {
    defineAllComponents();
  }
});

afterEach(() => {
  document.body.innerHTML = '';
});

describe('mk-radio', () => {
  it('registers under the tag name "mk-radio"', () => {
    const registered = getRegisteredComponents().find((c) => c.tagName === 'mk-radio');
    expect(registered).toBeDefined();
    expect(registered!.base).toBe(MkRadioElement);
  });

  it('MkRadioElement extends MkElement', () => {
    expect(Object.getPrototypeOf(MkRadioElement)).toBe(MkElement);
  });

  it('the size attribute reflects between property and the DOM attribute', async () => {
    const el = document.createElement('mk-radio') as MkRadioElement;
    const input = document.createElement('input');
    input.type = 'radio';
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

  it('does not remove its light-DOM input child after upgrade', async () => {
    const el = document.createElement('mk-radio') as MkRadioElement;
    const input = document.createElement('input');
    input.type = 'radio';
    input.name = 'color';
    input.value = 'red';
    el.appendChild(input);
    document.body.appendChild(el);
    await el.updateComplete;
    expect(el.querySelector('input[type="radio"]')).toBe(input);
    expect(input.value).toBe('red');
  });

  it('the documentation page exists at docs/src/content/docs/packages/theme-blank/mk-radio.md with the required sections', async () => {
    const fs = await import('fs');
    const path = await import('path');
    const docPath = path.resolve(
      __dirname,
      '../../../../../docs/src/content/docs/packages/theme-blank/mk-radio.md',
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
