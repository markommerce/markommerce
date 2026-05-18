// @vitest-environment happy-dom
import { afterEach, beforeAll, describe, expect, it, vi } from 'vitest';
import { defineAllComponents, getRegisteredComponents, MkElement } from '@markommerce/frontend';
import './mk-toast';
import { MkToastElement } from './mk-toast';

beforeAll(() => {
  if (!customElements.get('mk-toast')) {
    defineAllComponents();
  }
});

afterEach(() => {
  document.body.innerHTML = '';
  vi.useRealTimers();
});

describe('mk-toast', () => {
  it('it registers under the tag name "mk-toast" with MkToastElement extending MkElement', () => {
    const registered = getRegisteredComponents().find((c) => c.tagName === 'mk-toast');
    expect(registered).toBeDefined();
    expect(registered!.base).toBe(MkToastElement);
    expect(Object.getPrototypeOf(MkToastElement)).toBe(MkElement);
  });

  it('it reflects the variant attribute between property and DOM attribute', async () => {
    const el = document.createElement('mk-toast') as MkToastElement;
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

  it('it reflects the duration attribute as a number property', async () => {
    const el = document.createElement('mk-toast') as MkToastElement;
    document.body.appendChild(el);
    await el.updateComplete;

    // Default duration is 5000
    expect(el.duration).toBe(5000);

    el.duration = 3000;
    await el.updateComplete;
    expect(el.getAttribute('duration')).toBe('3000');

    el.setAttribute('duration', '8000');
    await el.updateComplete;
    expect(el.duration).toBe(8000);
  });

  it('it sets role="status" on connect when role is not already set', async () => {
    const el = document.createElement('mk-toast') as MkToastElement;
    document.body.appendChild(el);
    await el.updateComplete;

    expect(el.getAttribute('role')).toBe('status');
    expect(el.getAttribute('tabindex')).toBe('0');

    // Does not overwrite an existing role
    const el2 = document.createElement('mk-toast') as MkToastElement;
    el2.setAttribute('role', 'alert');
    document.body.appendChild(el2);
    await el2.updateComplete;
    expect(el2.getAttribute('role')).toBe('alert');
  });

  it('it injects a close button on connect when the dismissible attribute is present', async () => {
    const el = document.createElement('mk-toast') as MkToastElement;
    el.setAttribute('dismissible', '');
    document.body.appendChild(el);
    await el.updateComplete;

    const closeBtn = el.querySelector('button.mk-toast-close');
    expect(closeBtn).not.toBeNull();
    expect(closeBtn!.getAttribute('aria-label')).toBe('Dismiss');
  });

  it('it auto-removes the element after duration milliseconds when duration is a positive finite number', async () => {
    vi.useFakeTimers();

    const el = document.createElement('mk-toast') as MkToastElement;
    el.duration = 3000;
    document.body.appendChild(el);
    await el.updateComplete;

    expect(document.body.contains(el)).toBe(true);

    vi.advanceTimersByTime(2999);
    expect(document.body.contains(el)).toBe(true);

    vi.advanceTimersByTime(1);
    expect(document.body.contains(el)).toBe(false);
  });

  it('it does not auto-remove the element when duration is 0 or Infinity', async () => {
    vi.useFakeTimers();

    // duration = 0
    const el1 = document.createElement('mk-toast') as MkToastElement;
    el1.duration = 0;
    document.body.appendChild(el1);
    await el1.updateComplete;
    vi.advanceTimersByTime(100_000);
    expect(document.body.contains(el1)).toBe(true);

    // duration = Infinity
    const el2 = document.createElement('mk-toast') as MkToastElement;
    el2.duration = Infinity;
    document.body.appendChild(el2);
    await el2.updateComplete;
    vi.advanceTimersByTime(100_000);
    expect(document.body.contains(el2)).toBe(true);
  });

  it('it clears the auto-dismiss timer on disconnect', async () => {
    vi.useFakeTimers();

    const el = document.createElement('mk-toast') as MkToastElement;
    // Use Infinity so reconnect doesn't start a new timer
    el.duration = 3000;
    document.body.appendChild(el);
    await el.updateComplete;

    // Partially advance — timer not yet fired
    vi.advanceTimersByTime(1000);
    expect(document.body.contains(el)).toBe(true);

    // Disconnect — should clear the timer
    document.body.removeChild(el);

    // Override duration to Infinity so re-connect doesn't start another timer
    el.duration = Infinity;

    // Re-attach
    document.body.appendChild(el);
    await el.updateComplete;

    // Advance well past original 3000ms — old timer should have been cancelled
    vi.advanceTimersByTime(5000);
    expect(document.body.contains(el)).toBe(true);
  });
});
