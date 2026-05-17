// @vitest-environment happy-dom
import { afterEach, beforeAll, describe, expect, it } from 'vitest';
import { defineAllComponents, getRegisteredComponents } from '@markommerce/frontend';
import './mk-cluster';
import { MkClusterElement } from './mk-cluster';
import { MkElement } from '@markommerce/frontend';

beforeAll(() => {
  if (!customElements.get('mk-cluster')) {
    defineAllComponents();
  }
});

afterEach(() => {
  document.body.innerHTML = '';
});

describe('mk-cluster', () => {
  it('mk-cluster registers under the tag name "mk-cluster"', () => {
    const registered = getRegisteredComponents().find((c) => c.tagName === 'mk-cluster');
    expect(registered).toBeDefined();
    expect(registered!.base).toBe(MkClusterElement);
  });

  it('MkClusterElement extends MkElement', () => {
    expect(Object.getPrototypeOf(MkClusterElement)).toBe(MkElement);
  });

  it('the gap attribute reflects between the property and the DOM attribute', async () => {
    const el = document.createElement('mk-cluster') as MkClusterElement;
    document.body.appendChild(el);
    await el.updateComplete;

    el.gap = '4';
    await el.updateComplete;
    expect(el.getAttribute('gap')).toBe('4');

    el.setAttribute('gap', '6');
    await el.updateComplete;
    expect(el.gap).toBe('6');

    el.removeAttribute('gap');
    await el.updateComplete;
    expect(el.gap).toBeFalsy();
  });

  it('the align attribute reflects between the property and the DOM attribute', async () => {
    const el = document.createElement('mk-cluster') as MkClusterElement;
    document.body.appendChild(el);
    await el.updateComplete;

    el.align = 'center';
    await el.updateComplete;
    expect(el.getAttribute('align')).toBe('center');

    el.setAttribute('align', 'end');
    await el.updateComplete;
    expect(el.align).toBe('end');

    el.removeAttribute('align');
    await el.updateComplete;
    expect(el.align).toBeFalsy();
  });

  it('the justify attribute reflects between the property and the DOM attribute', async () => {
    const el = document.createElement('mk-cluster') as MkClusterElement;
    document.body.appendChild(el);
    await el.updateComplete;

    el.justify = 'center';
    await el.updateComplete;
    expect(el.getAttribute('justify')).toBe('center');

    el.setAttribute('justify', 'end');
    await el.updateComplete;
    expect(el.justify).toBe('end');

    el.removeAttribute('justify');
    await el.updateComplete;
    expect(el.justify).toBeFalsy();
  });

  it('appending mk-cluster to the DOM does not remove its light-DOM children', async () => {
    const el = document.createElement('mk-cluster') as MkClusterElement;
    const child = document.createElement('span');
    child.textContent = 'preserved';
    el.appendChild(child);
    document.body.appendChild(el);
    await el.updateComplete;

    expect(el.querySelector('span')).not.toBeNull();
    expect(el.querySelector('span')!.textContent).toBe('preserved');
  });

  it('the documentation page exists at docs/src/content/docs/packages/theme-blank/mk-cluster.md with the required sections', async () => {
    const fs = await import('node:fs');
    const path = await import('node:path');
    const { fileURLToPath } = await import('node:url');
    const __dirname = path.dirname(fileURLToPath(import.meta.url));
    const docPath = path.join(__dirname, '../../../../../docs/src/content/docs/packages/theme-blank/mk-cluster.md');

    expect(fs.existsSync(docPath), 'docs page should exist').toBe(true);

    const content = fs.readFileSync(docPath, 'utf-8');

    expect(content, 'should have frontmatter title').toContain('title: mk-cluster');
    expect(content, 'should have frontmatter description').toContain('description:');
    expect(content, 'should have HTML Usage section').toContain('## HTML Usage');
    expect(content, 'should have Attributes section').toContain('## Attributes');
    expect(content, 'should have Slots section').toContain('## Slots');
    expect(content, 'should have Events section').toContain('## Events');
    expect(content, 'should have CSS Custom Properties section').toContain('## CSS Custom Properties');
    expect(content, 'should have Variants & States section').toContain('## Variants & States');
    expect(content, 'should have Extending section').toContain('## Extending');
    expect(content, 'should have Accessibility section').toContain('## Accessibility');
    expect(content, 'should not have ## Overview heading').not.toContain('## Overview');
  });
});
