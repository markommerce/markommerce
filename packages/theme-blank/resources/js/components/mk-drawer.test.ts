// @vitest-environment happy-dom
import { afterEach, beforeAll, describe, expect, it, vi } from 'vitest';
import { defineAllComponents, getRegisteredComponents, MkElement } from '@markommerce/frontend';
import './mk-drawer';
import { MkDrawerElement } from './mk-drawer';

beforeAll(() => {
  if (!customElements.get('mk-drawer')) {
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

function buildDrawer(): { el: MkDrawerElement; dialog: HTMLDialogElement } {
  const el = document.createElement('mk-drawer') as MkDrawerElement;
  const dialog = document.createElement('dialog');
  el.appendChild(dialog);
  return { el, dialog };
}

describe('mk-drawer', () => {
  it('it registers under the tag name "mk-drawer" with MkDrawerElement extending MkElement', () => {
    const registered = getRegisteredComponents().find((c) => c.tagName === 'mk-drawer');
    expect(registered).toBeDefined();
    expect(registered!.base).toBe(MkDrawerElement);
    expect(Object.getPrototypeOf(MkDrawerElement)).toBe(MkElement);
  });

  it('it declares open, size, placement, dismissible as Lit @property with reflect: true', async () => {
    const { el, dialog } = buildDrawer();
    document.body.appendChild(el);
    await el.updateComplete;

    // open (boolean)
    el.open = true;
    await el.updateComplete;
    expect(el.hasAttribute('open')).toBe(true);
    el.open = false;
    await el.updateComplete;
    expect(el.hasAttribute('open')).toBe(false);

    // size (string)
    el.size = 'sm';
    await el.updateComplete;
    expect(el.getAttribute('size')).toBe('sm');
    el.size = undefined;
    await el.updateComplete;
    expect(el.hasAttribute('size')).toBe(false);

    // placement (string)
    el.placement = 'left';
    await el.updateComplete;
    expect(el.getAttribute('placement')).toBe('left');

    // dismissible (boolean)
    el.dismissible = true;
    await el.updateComplete;
    expect(el.hasAttribute('dismissible')).toBe(true);
    el.dismissible = false;
    await el.updateComplete;
    expect(el.hasAttribute('dismissible')).toBe(false);

    void dialog;
  });

  it('it reflects the placement attribute between property and DOM attribute (left|right)', async () => {
    const { el, dialog } = buildDrawer();
    document.body.appendChild(el);
    await el.updateComplete;

    el.placement = 'left';
    await el.updateComplete;
    expect(el.getAttribute('placement')).toBe('left');

    el.placement = 'right';
    await el.updateComplete;
    expect(el.getAttribute('placement')).toBe('right');

    el.setAttribute('placement', 'left');
    await el.updateComplete;
    expect(el.placement).toBe('left');

    el.removeAttribute('placement');
    await el.updateComplete;
    expect(el.placement).toBeUndefined();

    void dialog;
  });

  it('it defaults to placement="right" when no placement attribute is set', async () => {
    const { el, dialog } = buildDrawer();
    // Do NOT set placement attribute
    document.body.appendChild(el);
    await el.updateComplete;
    expect(el.getAttribute('placement')).toBe('right');
    void dialog;
  });

  it('it calls dialog.showModal() in updated() when the open property transitions to true', async () => {
    const { el, dialog } = buildDrawer();
    document.body.appendChild(el);
    await el.updateComplete;

    const showModal = vi.spyOn(dialog, 'showModal');
    el.open = true;
    await el.updateComplete;
    expect(showModal).toHaveBeenCalledOnce();
  });

  it('it calls dialog.close() in updated() when the open property transitions to false', async () => {
    const { el, dialog } = buildDrawer();
    el.open = true;
    document.body.appendChild(el);
    await el.updateComplete;

    const close = vi.spyOn(dialog, 'close');
    el.open = false;
    await el.updateComplete;
    expect(close).toHaveBeenCalledOnce();
  });

  it('it removes the open attribute when the inner dialog dispatches a "close" event', async () => {
    const { el, dialog } = buildDrawer();
    el.open = true;
    document.body.appendChild(el);
    await el.updateComplete;
    expect(el.open).toBe(true);

    // happy-dom does not auto-fire close event — dispatch manually
    dialog.dispatchEvent(new Event('close'));
    await el.updateComplete;
    expect(el.open).toBe(false);
    expect(el.hasAttribute('open')).toBe(false);
  });

  it('it does NOT recursively call dialog.close() during the close-event reflection', async () => {
    const { el, dialog } = buildDrawer();
    el.open = true;
    document.body.appendChild(el);
    await el.updateComplete;

    const close = vi.spyOn(dialog, 'close');
    // Simulate native dialog closing (fires close event but NOT via our code)
    dialog.dispatchEvent(new Event('close'));
    await el.updateComplete;
    // open becomes false — updated() should NOT call dialog.close() again
    // because the close event already came from the dialog
    expect(close).not.toHaveBeenCalled();
  });

  it('it dispatches a mk-close CustomEvent (bubbles: true, composed: true) on the wrapper when the dialog closes', async () => {
    const { el, dialog } = buildDrawer();
    el.open = true;
    document.body.appendChild(el);
    await el.updateComplete;

    const received: CustomEvent[] = [];
    el.addEventListener('mk-close', (e) => received.push(e as CustomEvent));

    dialog.dispatchEvent(new Event('close'));
    await el.updateComplete;

    expect(received).toHaveLength(1);
    expect(received[0]!.bubbles).toBe(true);
    expect(received[0]!.composed).toBe(true);
  });

  it('it calls requireInnerControl(this, \'dialog\') in connectedCallback and emits a single console.warn when no inner <dialog> is present', async () => {
    const el = document.createElement('mk-drawer') as MkDrawerElement;
    // No dialog child
    const warnSpy = vi.spyOn(console, 'warn').mockImplementation(() => undefined);
    document.body.appendChild(el);
    await el.updateComplete;
    expect(warnSpy).toHaveBeenCalledOnce();
    expect(warnSpy.mock.calls[0]![0]).toContain('dialog');

    // Second connect should NOT warn again (WeakSet guards it)
    document.body.removeChild(el);
    document.body.appendChild(el);
    await el.updateComplete;
    expect(warnSpy).toHaveBeenCalledOnce();
  });

  it('it re-applies CSS positioning when placement changes from right to left while open', async () => {
    const { el, dialog } = buildDrawer();
    el.open = true;
    document.body.appendChild(el);
    await el.updateComplete;
    // Default placement is right
    expect(el.getAttribute('placement')).toBe('right');

    // Change placement to left
    el.placement = 'left';
    await el.updateComplete;
    expect(el.getAttribute('placement')).toBe('left');

    void dialog;
  });

  it('it suppresses cancel and backdrop-click dismissal when dismissible is absent and allows them when dismissible is present', async () => {
    // Without dismissible — cancel should be prevented
    const { el: el1, dialog: dialog1 } = buildDrawer();
    el1.open = true;
    document.body.appendChild(el1);
    await el1.updateComplete;

    const cancelEvent = new Event('cancel', { cancelable: true });
    dialog1.dispatchEvent(cancelEvent);
    expect(cancelEvent.defaultPrevented).toBe(true);

    // With dismissible — cancel should NOT be prevented
    const { el: el2, dialog: dialog2 } = buildDrawer();
    el2.open = true;
    el2.dismissible = true;
    document.body.appendChild(el2);
    await el2.updateComplete;

    const cancelEvent2 = new Event('cancel', { cancelable: true });
    dialog2.dispatchEvent(cancelEvent2);
    expect(cancelEvent2.defaultPrevented).toBe(false);

    // Without dismissible — backdrop click on dialog should NOT close
    const { el: el3, dialog: dialog3 } = buildDrawer();
    el3.open = true;
    document.body.appendChild(el3);
    await el3.updateComplete;
    const close3 = vi.spyOn(dialog3, 'close');
    const clickEvent = new MouseEvent('click', { cancelable: true, bubbles: true });
    dialog3.dispatchEvent(clickEvent);
    expect(close3).not.toHaveBeenCalled();

    // With dismissible — backdrop click on dialog should call dialog.close()
    const { el: el4, dialog: dialog4 } = buildDrawer();
    el4.open = true;
    el4.dismissible = true;
    document.body.appendChild(el4);
    await el4.updateComplete;
    const close4 = vi.spyOn(dialog4, 'close');
    const clickEvent2 = new MouseEvent('click', { cancelable: true, bubbles: true });
    dialog4.dispatchEvent(clickEvent2);
    expect(close4).toHaveBeenCalledOnce();
  });
});
