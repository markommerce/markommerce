// @vitest-environment happy-dom
import { afterEach, beforeAll, describe, expect, it } from 'vitest';
import { defineAllComponents, getRegisteredComponents } from '@markommerce/frontend';
import './mk-fieldset';
import { MkFieldsetElement } from './mk-fieldset';
import { MkElement } from '@markommerce/frontend';

beforeAll(() => {
  if (!customElements.get('mk-fieldset')) {
    defineAllComponents();
  }
});

afterEach(() => {
  document.body.innerHTML = '';
});

describe('mk-fieldset', () => {
  it('it registers under the tag name "mk-fieldset"', () => {
    const registered = getRegisteredComponents().find((c) => c.tagName === 'mk-fieldset');
    expect(registered).toBeDefined();
    expect(registered!.base).toBe(MkFieldsetElement);
  });

  it('it MkFieldsetElement extends MkElement', () => {
    expect(Object.getPrototypeOf(MkFieldsetElement)).toBe(MkElement);
  });

  it('it does not remove its light-DOM fieldset child after upgrade', async () => {
    const el = document.createElement('mk-fieldset') as MkFieldsetElement;
    const fieldset = document.createElement('fieldset');
    el.appendChild(fieldset);
    document.body.appendChild(el);
    await el.updateComplete;

    expect(el.querySelector('fieldset')).not.toBeNull();
  });

  it('it the documentation page exists at docs/src/content/docs/packages/theme-blank/mk-fieldset.md with the required sections', async () => {
    const fs = await import('fs');
    const path = await import('path');
    const docPath = path.resolve(
      __dirname,
      '../../../../../docs/src/content/docs/packages/theme-blank/mk-fieldset.md',
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
