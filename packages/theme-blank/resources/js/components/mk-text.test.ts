// @vitest-environment happy-dom
import { afterEach, beforeAll, describe, expect, it } from 'vitest';
import { defineAllComponents, getRegisteredComponents } from '@markommerce/frontend';
import './mk-text';
import { MkTextElement } from './mk-text';
import { MkElement } from '@markommerce/frontend';

beforeAll(() => {
  if (!customElements.get('mk-text')) {
    defineAllComponents();
  }
});

afterEach(() => {
  document.body.innerHTML = '';
});

describe('mk-text', () => {
  it('mk-text registers under the tag name "mk-text"', () => {
    const registered = getRegisteredComponents().find((c) => c.tagName === 'mk-text');
    expect(registered).toBeDefined();
    expect(registered!.base).toBe(MkTextElement);
  });

  it('MkTextElement extends MkElement', () => {
    expect(Object.getPrototypeOf(MkTextElement)).toBe(MkElement);
  });

  it('the variant attribute reflects between the property and the DOM attribute', async () => {
    const el = document.createElement('mk-text') as MkTextElement;
    document.body.appendChild(el);
    await el.updateComplete;

    el.variant = 'lead';
    await el.updateComplete;
    expect(el.getAttribute('variant')).toBe('lead');

    el.setAttribute('variant', 'small');
    await el.updateComplete;
    expect(el.variant).toBe('small');

    el.removeAttribute('variant');
    await el.updateComplete;
    expect(el.variant).toBeFalsy();
  });

  it('the weight attribute reflects between the property and the DOM attribute', async () => {
    const el = document.createElement('mk-text') as MkTextElement;
    document.body.appendChild(el);
    await el.updateComplete;

    el.weight = 'bold';
    await el.updateComplete;
    expect(el.getAttribute('weight')).toBe('bold');

    el.setAttribute('weight', 'medium');
    await el.updateComplete;
    expect(el.weight).toBe('medium');

    el.removeAttribute('weight');
    await el.updateComplete;
    expect(el.weight).toBeFalsy();
  });

  it('appending <mk-text>Hello</mk-text> to the DOM preserves the text node child unchanged', async () => {
    document.body.innerHTML = '<mk-text>Hello</mk-text>';
    const el = document.body.querySelector('mk-text') as MkTextElement;
    await el.updateComplete;
    expect(el.textContent).toBe('Hello');
  });

  it('appending <mk-text><p>Hello</p></mk-text> to the DOM preserves the inner <p> child unchanged', async () => {
    document.body.innerHTML = '<mk-text><p>Hello</p></mk-text>';
    const el = document.body.querySelector('mk-text') as MkTextElement;
    await el.updateComplete;
    const p = el.querySelector('p');
    expect(p).not.toBeNull();
    expect(p!.textContent).toBe('Hello');
  });

  it('the documentation page exists at docs/src/content/docs/packages/theme-blank/mk-text.md with the required sections', async () => {
    const fs = await import('fs');
    const path = await import('path');
    const docPath = path.resolve(
      __dirname,
      '../../../../../docs/src/content/docs/packages/theme-blank/mk-text.md',
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
