// @vitest-environment happy-dom
import { afterEach, beforeAll, describe, expect, it, vi } from 'vitest';
import { defineAllComponents, getRegisteredComponents } from '@markommerce/frontend';
import './mk-modal';
import { MkModalElement } from './mk-modal';
import { MkElement } from '@markommerce/frontend';

beforeAll(() => {
  if (!customElements.get('mk-modal')) {
    defineAllComponents();
  }
  // Stub HTMLDialogElement methods (not implemented in happy-dom)
  if (!HTMLDialogElement.prototype.showModal) {
    HTMLDialogElement.prototype.showModal = vi.fn();
  }
  if (!HTMLDialogElement.prototype.close) {
    HTMLDialogElement.prototype.close = vi.fn();
  }
});

afterEach(() => {
  document.body.innerHTML = '';
  vi.restoreAllMocks();
});

function buildModal(): { el: MkModalElement; dialog: HTMLDialogElement } {
  const el = document.createElement('mk-modal') as MkModalElement;
  const dialog = document.createElement('dialog');
  el.appendChild(dialog);
  return { el, dialog };
}

describe('mk-modal', () => {
  it('it registers under the tag name "mk-modal" with MkModalElement extending MkElement', () => {
    const registered = getRegisteredComponents().find((c) => c.tagName === 'mk-modal');
    expect(registered).toBeDefined();
    expect(registered!.base).toBe(MkModalElement);
    expect(Object.getPrototypeOf(MkModalElement)).toBe(MkElement);
  });

  it('it calls requireInnerControl(this, \'dialog\') in connectedCallback and emits a single console.warn when no inner <dialog> is present', async () => {
    const warnSpy = vi.spyOn(console, 'warn').mockImplementation(() => {});
    const el = document.createElement('mk-modal') as MkModalElement;
    // No dialog child
    document.body.appendChild(el);
    await el.updateComplete;

    expect(warnSpy).toHaveBeenCalledOnce();
    expect(warnSpy).toHaveBeenCalledWith(
      expect.stringContaining('mk-modal'),
    );
    expect(warnSpy).toHaveBeenCalledWith(
      expect.stringContaining('dialog'),
    );

    // Re-connecting should NOT emit a second warning (WeakSet guard in requireInnerControl)
    document.body.removeChild(el);
    document.body.appendChild(el);
    await el.updateComplete;

    expect(warnSpy).toHaveBeenCalledOnce();
  });

  it('it closes the dialog on backdrop click (target===dialog) when dismissible is present', async () => {
    const { el, dialog } = buildModal();
    const closeSpy = vi.spyOn(dialog, 'close');
    el.dismissible = true;
    document.body.appendChild(el);
    await el.updateComplete;

    // Open the modal first
    el.open = true;
    await el.updateComplete;

    // Simulate backdrop click (target === dialog itself)
    const clickEvent = new MouseEvent('click', { bubbles: true });
    Object.defineProperty(clickEvent, 'target', { value: dialog });
    dialog.dispatchEvent(clickEvent);

    expect(closeSpy).toHaveBeenCalledOnce();
  });

  it('it ignores a backdrop click (target===dialog) when dismissible is absent', async () => {
    const { el, dialog } = buildModal();
    const closeSpy = vi.spyOn(dialog, 'close');
    document.body.appendChild(el);
    await el.updateComplete;

    // dismissible is absent (false by default)
    expect(el.dismissible).toBe(false);

    // Open the modal first
    el.open = true;
    await el.updateComplete;

    // Simulate backdrop click (target === dialog itself)
    const clickEvent = new MouseEvent('click', { bubbles: true });
    Object.defineProperty(clickEvent, 'target', { value: dialog });
    dialog.dispatchEvent(clickEvent);

    expect(closeSpy).not.toHaveBeenCalled();
  });

  it('it allows native ESC dismissal (does not preventDefault) when dismissible is present', async () => {
    const { el, dialog } = buildModal();
    el.dismissible = true;
    document.body.appendChild(el);
    await el.updateComplete;

    const cancelEvent = new Event('cancel', { cancelable: true });
    dialog.dispatchEvent(cancelEvent);

    expect(cancelEvent.defaultPrevented).toBe(false);
  });

  it('it suppresses native ESC dismissal by calling event.preventDefault() on the cancel event when dismissible is absent', async () => {
    const { el, dialog } = buildModal();
    document.body.appendChild(el);
    await el.updateComplete;

    // dismissible is absent (false by default)
    expect(el.dismissible).toBe(false);

    const cancelEvent = new Event('cancel', { cancelable: true });
    dialog.dispatchEvent(cancelEvent);

    expect(cancelEvent.defaultPrevented).toBe(true);
  });

  it('it dispatches a mk-close CustomEvent (bubbles: true, composed: true) on the wrapper when the dialog closes', async () => {
    const { el, dialog } = buildModal();
    document.body.appendChild(el);
    await el.updateComplete;

    // Open the modal
    el.open = true;
    await el.updateComplete;

    const events: CustomEvent[] = [];
    el.addEventListener('mk-close', (e) => events.push(e as CustomEvent));

    // Simulate dialog close event
    dialog.dispatchEvent(new Event('close'));
    // Wait for async close handler to complete
    await new Promise((r) => setTimeout(r, 0));
    await el.updateComplete;

    expect(events).toHaveLength(1);
    expect(events[0]!.bubbles).toBe(true);
    expect(events[0]!.composed).toBe(true);
  });

  it('it does NOT recursively call dialog.close() during the close-event reflection (guard prevents the loop)', async () => {
    const { el, dialog } = buildModal();
    const closeSpy = vi.spyOn(dialog, 'close');
    document.body.appendChild(el);
    await el.updateComplete;

    // Open the modal
    el.open = true;
    await el.updateComplete;

    // Simulate dialog close event (e.g., from native ESC press)
    // During the close event handling, #reflectingClose = true, so dialog.close() should NOT be called again
    dialog.dispatchEvent(new Event('close'));
    await el.updateComplete;

    // dialog.close() should NOT have been called (only the native close event fired, not our code)
    expect(closeSpy).not.toHaveBeenCalled();
  });

  it('it removes the open attribute (sets the open property to false) when the inner dialog dispatches a "close" event', async () => {
    const { el, dialog } = buildModal();
    document.body.appendChild(el);
    await el.updateComplete;

    // Open the modal
    el.open = true;
    await el.updateComplete;
    expect(el.open).toBe(true);

    // Simulate dialog close event (e.g., from native ESC)
    dialog.dispatchEvent(new Event('close'));
    await el.updateComplete;

    expect(el.open).toBe(false);
    expect(el.hasAttribute('open')).toBe(false);
  });

  it('it calls dialog.close() in updated() when the open property transitions to false', async () => {
    const { el, dialog } = buildModal();
    const closeSpy = vi.spyOn(dialog, 'close');
    document.body.appendChild(el);
    await el.updateComplete;

    // First open it
    el.open = true;
    await el.updateComplete;

    // Then close it
    el.open = false;
    await el.updateComplete;

    expect(closeSpy).toHaveBeenCalledOnce();
  });

  it('it calls dialog.showModal() in updated() when the open property transitions to true', async () => {
    const { el, dialog } = buildModal();
    const showModalSpy = vi.spyOn(dialog, 'showModal');
    document.body.appendChild(el);
    await el.updateComplete;

    el.open = true;
    await el.updateComplete;

    expect(showModalSpy).toHaveBeenCalledOnce();
  });

  it('it declares dismissible as a Lit @property with type: Boolean and reflect: true', async () => {
    const el = document.createElement('mk-modal') as MkModalElement;
    document.body.appendChild(el);
    await el.updateComplete;

    // Property should default to false
    expect(el.dismissible).toBe(false);

    // Setting property should reflect to attribute
    el.dismissible = true;
    await el.updateComplete;
    expect(el.hasAttribute('dismissible')).toBe(true);

    el.dismissible = false;
    await el.updateComplete;
    expect(el.hasAttribute('dismissible')).toBe(false);

    // Setting attribute should reflect to property
    el.setAttribute('dismissible', '');
    await el.updateComplete;
    expect(el.dismissible).toBe(true);

    el.removeAttribute('dismissible');
    await el.updateComplete;
    expect(el.dismissible).toBe(false);
  });

  it('it declares size as a Lit @property with reflect: true (sm|md|lg)', async () => {
    const el = document.createElement('mk-modal') as MkModalElement;
    document.body.appendChild(el);
    await el.updateComplete;

    // Property should default to undefined
    expect(el.size).toBeUndefined();

    // Setting property should reflect to attribute
    el.size = 'sm';
    await el.updateComplete;
    expect(el.getAttribute('size')).toBe('sm');

    el.size = 'md';
    await el.updateComplete;
    expect(el.getAttribute('size')).toBe('md');

    el.size = 'lg';
    await el.updateComplete;
    expect(el.getAttribute('size')).toBe('lg');

    // Setting attribute should reflect to property
    el.setAttribute('size', 'sm');
    await el.updateComplete;
    expect(el.size).toBe('sm');

    el.removeAttribute('size');
    await el.updateComplete;
    expect(el.size).toBeUndefined();
  });

  it('it declares open as a Lit @property with type: Boolean and reflect: true', async () => {
    const el = document.createElement('mk-modal') as MkModalElement;
    document.body.appendChild(el);
    await el.updateComplete;

    // Property should default to false
    expect(el.open).toBe(false);

    // Setting property should reflect to attribute
    el.open = true;
    await el.updateComplete;
    expect(el.hasAttribute('open')).toBe(true);

    el.open = false;
    await el.updateComplete;
    expect(el.hasAttribute('open')).toBe(false);

    // Setting attribute should reflect to property
    el.setAttribute('open', '');
    await el.updateComplete;
    expect(el.open).toBe(true);

    el.removeAttribute('open');
    await el.updateComplete;
    expect(el.open).toBe(false);
  });
});
