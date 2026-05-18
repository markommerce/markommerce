// @vitest-environment happy-dom
import { afterEach, beforeAll, describe, expect, it } from 'vitest';
import { defineAllComponents, getRegisteredComponents } from '@markommerce/frontend';
import './mk-textarea';
import { MkTextareaElement } from './mk-textarea';
import { MkElement } from '@markommerce/frontend';

beforeAll(() => {
  if (!customElements.get('mk-textarea')) {
    defineAllComponents();
  }
});

afterEach(() => {
  document.body.innerHTML = '';
});

describe('mk-textarea', () => {
  it('registers under the tag name "mk-textarea"', () => {
    const registered = getRegisteredComponents().find((c) => c.tagName === 'mk-textarea');
    expect(registered).toBeDefined();
    expect(registered!.base).toBe(MkTextareaElement);
  });

  it('MkTextareaElement extends MkElement', () => {
    expect(Object.getPrototypeOf(MkTextareaElement)).toBe(MkElement);
  });

  it('the variant attribute reflects between property and the DOM attribute', async () => {
    const el = document.createElement('mk-textarea') as MkTextareaElement;
    document.body.appendChild(el);
    await el.updateComplete;

    el.variant = 'outline';
    await el.updateComplete;
    expect(el.getAttribute('variant')).toBe('outline');

    el.removeAttribute('variant');
    await el.updateComplete;
    expect(el.variant).toBeUndefined();
  });

  it('the size attribute reflects between property and the DOM attribute', async () => {
    const el = document.createElement('mk-textarea') as MkTextareaElement;
    document.body.appendChild(el);
    await el.updateComplete;

    el.size = 'lg';
    await el.updateComplete;
    expect(el.getAttribute('size')).toBe('lg');

    el.removeAttribute('size');
    await el.updateComplete;
    expect(el.size).toBeUndefined();
  });

  it('does not remove its light-DOM textarea child after upgrade', async () => {
    const el = document.createElement('mk-textarea') as MkTextareaElement;
    const textarea = document.createElement('textarea');
    el.appendChild(textarea);
    document.body.appendChild(el);
    await el.updateComplete;

    expect(el.querySelector('textarea')).not.toBeNull();
  });

  it('the documentation page exists at docs/src/content/docs/packages/theme-blank/mk-textarea.md with the required sections', async () => {
    const fs = await import('fs');
    const path = await import('path');
    const docPath = path.resolve(
      __dirname,
      '../../../../../docs/src/content/docs/packages/theme-blank/mk-textarea.md',
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
