// @vitest-environment happy-dom
import { afterEach, beforeAll, describe, expect, it } from 'vitest';
import { defineAllComponents, getRegisteredComponents } from '@markommerce/frontend';
import './mk-divider';
import { MkDividerElement } from './mk-divider';
import { MkElement } from '@markommerce/frontend';

beforeAll(() => {
  if (!customElements.get('mk-divider')) {
    defineAllComponents();
  }
});

afterEach(() => {
  document.body.innerHTML = '';
});

describe('mk-divider', () => {
  it('mk-divider registers under the tag name "mk-divider"', () => {
    const registered = getRegisteredComponents().find((c) => c.tagName === 'mk-divider');
    expect(registered).toBeDefined();
    expect(registered!.base).toBe(MkDividerElement);
  });

  it('MkDividerElement extends MkElement', () => {
    expect(Object.getPrototypeOf(MkDividerElement)).toBe(MkElement);
  });

  it('the orientation attribute reflects between the property and the DOM attribute', async () => {
    const el = document.createElement('mk-divider') as MkDividerElement;
    document.body.appendChild(el);
    await el.updateComplete;

    el.orientation = 'vertical';
    await el.updateComplete;
    expect(el.getAttribute('orientation')).toBe('vertical');

    el.removeAttribute('orientation');
    await el.updateComplete;
    expect(el.orientation).toBeUndefined();
  });

  it('the element gains role="separator" automatically when connected', () => {
    const el = document.createElement('mk-divider') as MkDividerElement;
    document.body.appendChild(el);
    expect(el.getAttribute('role')).toBe('separator');
  });

  it('the element gains aria-orientation="vertical" when orientation="vertical" and connected', () => {
    const el = document.createElement('mk-divider') as MkDividerElement;
    el.orientation = 'vertical';
    document.body.appendChild(el);
    expect(el.getAttribute('aria-orientation')).toBe('vertical');
  });

  it('the element does not override an existing role attribute set by the consumer', () => {
    const el = document.createElement('mk-divider') as MkDividerElement;
    el.setAttribute('role', 'presentation');
    document.body.appendChild(el);
    expect(el.getAttribute('role')).toBe('presentation');
  });

  it('the documentation page exists at docs/src/content/docs/packages/theme-blank/mk-divider.md with the required sections', async () => {
    const fs = await import('fs');
    const path = await import('path');
    const docPath = path.resolve(
      __dirname,
      '../../../../../docs/src/content/docs/packages/theme-blank/mk-divider.md',
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
