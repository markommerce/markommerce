// @vitest-environment happy-dom
import { afterEach, beforeAll, describe, expect, it } from 'vitest';
import { defineAllComponents, getRegisteredComponents } from '@markommerce/frontend';
import './mk-alert';
import { MkAlertElement } from './mk-alert';
import { MkElement } from '@markommerce/frontend';

beforeAll(() => {
  if (!customElements.get('mk-alert')) {
    defineAllComponents();
  }
});

afterEach(() => {
  document.body.innerHTML = '';
});

describe('mk-alert', () => {
  it('it registers under the tag name "mk-alert" with MkAlertElement extending MkElement', () => {
    const registered = getRegisteredComponents().find((c) => c.tagName === 'mk-alert');
    expect(registered).toBeDefined();
    expect(registered!.base).toBe(MkAlertElement);
    expect(Object.getPrototypeOf(MkAlertElement)).toBe(MkElement);
  });

  it('it reflects the variant attribute between property and DOM attribute', async () => {
    const el = document.createElement('mk-alert') as MkAlertElement;
    document.body.appendChild(el);
    await el.updateComplete;

    el.variant = 'info';
    await el.updateComplete;
    expect(el.getAttribute('variant')).toBe('info');

    el.variant = 'success';
    await el.updateComplete;
    expect(el.getAttribute('variant')).toBe('success');

    el.setAttribute('variant', 'warning');
    await el.updateComplete;
    expect(el.variant).toBe('warning');

    el.removeAttribute('variant');
    await el.updateComplete;
    expect(el.variant).toBeUndefined();
  });

  it('it preserves server-rendered child content (does not replace innerHTML)', async () => {
    const el = document.createElement('mk-alert') as MkAlertElement;
    const p = document.createElement('p');
    p.textContent = 'This is an alert message.';
    el.appendChild(p);
    document.body.appendChild(el);
    await el.updateComplete;

    expect(el.querySelector('p')).not.toBeNull();
    expect(el.querySelector('p')!.textContent).toBe('This is an alert message.');
  });

  it('it injects a close button on connect when the dismissible attribute is present', async () => {
    const el = document.createElement('mk-alert') as MkAlertElement;
    el.setAttribute('dismissible', '');
    document.body.appendChild(el);
    await el.updateComplete;

    const closeBtn = el.querySelector('button.mk-alert-close');
    expect(closeBtn).not.toBeNull();
    expect(closeBtn!.getAttribute('aria-label')).toBe('Dismiss');
  });

  it('it does not inject a close button when the dismissible attribute is absent', async () => {
    const el = document.createElement('mk-alert') as MkAlertElement;
    document.body.appendChild(el);
    await el.updateComplete;

    const closeBtn = el.querySelector('button.mk-alert-close');
    expect(closeBtn).toBeNull();
  });

  it('it removes the element from the DOM when the injected close button is clicked', async () => {
    const el = document.createElement('mk-alert') as MkAlertElement;
    el.setAttribute('dismissible', '');
    document.body.appendChild(el);
    await el.updateComplete;

    expect(document.body.contains(el)).toBe(true);

    const closeBtn = el.querySelector('button.mk-alert-close') as HTMLButtonElement;
    closeBtn.click();

    expect(document.body.contains(el)).toBe(false);
  });

  it('it does not inject a duplicate close button when re-connected to the DOM', async () => {
    const el = document.createElement('mk-alert') as MkAlertElement;
    el.setAttribute('dismissible', '');
    document.body.appendChild(el);
    await el.updateComplete;

    document.body.removeChild(el);
    document.body.appendChild(el);
    await el.updateComplete;

    const buttons = el.querySelectorAll('button.mk-alert-close');
    expect(buttons.length).toBe(1);
  });
});
