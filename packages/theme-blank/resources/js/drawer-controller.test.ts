// @vitest-environment happy-dom
import { afterEach, beforeAll, describe, expect, it, vi } from 'vitest';
import { defineAllComponents } from '@markommerce/frontend';
import './components/mk-drawer';
import { openDrawer } from './drawer-controller';

beforeAll(() => {
  if (!customElements.get('mk-drawer')) {
    defineAllComponents();
  }
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

describe('drawer-controller', () => {
  it('creates and appends a fresh <mk-drawer> element to document.body', () => {
    openDrawer('content');
    const drawer = document.body.querySelector('mk-drawer');
    expect(drawer).not.toBeNull();
  });

  it('sets the size attribute on the drawer when options.size is provided', () => {
    openDrawer('content', { size: 'lg' });
    const drawer = document.body.querySelector('mk-drawer');
    expect(drawer?.getAttribute('size')).toBe('lg');
  });

  it('sets the placement attribute on the drawer when options.placement is provided', () => {
    openDrawer('content', { placement: 'left' });
    const drawer = document.body.querySelector('mk-drawer');
    expect(drawer?.getAttribute('placement')).toBe('left');
  });

  it('defaults to placement="right" when options.placement is absent', () => {
    openDrawer('content');
    const drawer = document.body.querySelector('mk-drawer');
    expect(drawer?.getAttribute('placement')).toBe('right');
  });

  it('sets the dismissible attribute when options.dismissible is true', () => {
    openDrawer('content', { dismissible: true });
    const drawer = document.body.querySelector('mk-drawer');
    expect(drawer?.hasAttribute('dismissible')).toBe(true);
  });

  it('appends HTMLElement content to the inner dialog and assigns string content via innerHTML', () => {
    const el = document.createElement('span');
    el.textContent = 'hello';
    openDrawer(el);
    const dialog = document.body.querySelector('mk-drawer dialog');
    expect(dialog?.querySelector('span')?.textContent).toBe('hello');

    document.body.innerHTML = '';
    openDrawer('<p>world</p>');
    const dialog2 = document.body.querySelector('mk-drawer dialog');
    expect(dialog2?.innerHTML).toBe('<p>world</p>');
  });

  it('sets the open attribute on the wrapper to trigger showModal', () => {
    openDrawer('content');
    const drawer = document.body.querySelector('mk-drawer');
    expect(drawer?.hasAttribute('open')).toBe(true);
  });

  it('returns a DrawerHandle whose close() removes the open attribute', () => {
    const handle = openDrawer('content');
    const drawer = document.body.querySelector('mk-drawer');
    expect(drawer?.hasAttribute('open')).toBe(true);
    handle.close();
    expect(drawer?.hasAttribute('open')).toBe(false);
  });

  it('removes the entire <mk-drawer> from the DOM when the mk-close event fires', async () => {
    openDrawer('content');
    const drawer = document.body.querySelector('mk-drawer');
    expect(drawer).not.toBeNull();
    const dialog = drawer?.querySelector('dialog') as HTMLDialogElement;
    dialog.dispatchEvent(new Event('close'));
    await (drawer as Element & { updateComplete?: Promise<unknown> }).updateComplete;
    expect(document.body.querySelector('mk-drawer')).toBeNull();
  });
});
