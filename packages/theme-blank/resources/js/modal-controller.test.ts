// @vitest-environment happy-dom
import { afterEach, beforeAll, describe, expect, it, vi } from 'vitest';
import { defineAllComponents } from '@markommerce/frontend';
import './components/mk-modal';

beforeAll(() => {
  if (!customElements.get('mk-modal')) {
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

describe('modal-controller', () => {
  it('creates and appends a fresh <mk-modal> element to document.body', async () => {
    const { openModal } = await import('./modal-controller');
    openModal('hello');
    const wrapper = document.body.querySelector('mk-modal');
    expect(wrapper).not.toBeNull();
  });

  it('sets the size attribute on the modal when options.size is provided', async () => {
    const { openModal } = await import('./modal-controller');
    openModal('hello', { size: 'lg' });
    const wrapper = document.body.querySelector('mk-modal');
    expect(wrapper?.getAttribute('size')).toBe('lg');
  });

  it('sets the dismissible attribute on the modal when options.dismissible is true', async () => {
    const { openModal } = await import('./modal-controller');
    openModal('hello', { dismissible: true });
    const wrapper = document.body.querySelector('mk-modal');
    expect(wrapper?.hasAttribute('dismissible')).toBe(true);
  });

  it('does not set the dismissible attribute when options.dismissible is false or absent', async () => {
    const { openModal } = await import('./modal-controller');
    openModal('hello', { dismissible: false });
    const wrapperFalse = document.body.querySelector('mk-modal');
    expect(wrapperFalse?.hasAttribute('dismissible')).toBe(false);
    document.body.innerHTML = '';
    openModal('hello');
    const wrapperAbsent = document.body.querySelector('mk-modal');
    expect(wrapperAbsent?.hasAttribute('dismissible')).toBe(false);
  });

  it('appends an HTMLElement content argument to the inner dialog via appendChild', async () => {
    const { openModal } = await import('./modal-controller');
    const content = document.createElement('p');
    content.textContent = 'paragraph content';
    openModal(content);
    const dialog = document.body.querySelector('mk-modal dialog');
    expect(dialog?.contains(content)).toBe(true);
  });

  it('assigns a string content argument to the inner dialog via innerHTML', async () => {
    const { openModal } = await import('./modal-controller');
    openModal('<span>hello world</span>');
    const dialog = document.body.querySelector('mk-modal dialog');
    expect(dialog?.innerHTML).toBe('<span>hello world</span>');
  });

  it('sets the open attribute on the wrapper to trigger the dialog showModal call', async () => {
    const { openModal } = await import('./modal-controller');
    const showModalSpy = vi.spyOn(HTMLDialogElement.prototype, 'showModal').mockImplementation(() => {});
    openModal('hello');
    const wrapper = document.body.querySelector('mk-modal') as HTMLElement & { open: boolean };
    await (wrapper as unknown as { updateComplete: Promise<void> }).updateComplete;
    expect(wrapper.open).toBe(true);
    expect(showModalSpy).toHaveBeenCalledOnce();
  });

  it('returns a ModalHandle whose close() method removes the open attribute from the wrapper', async () => {
    const { openModal } = await import('./modal-controller');
    vi.spyOn(HTMLDialogElement.prototype, 'showModal').mockImplementation(() => {});
    vi.spyOn(HTMLDialogElement.prototype, 'close').mockImplementation(() => {});
    const handle = openModal('hello');
    const wrapper = document.body.querySelector('mk-modal') as HTMLElement & { open: boolean };
    await (wrapper as unknown as { updateComplete: Promise<void> }).updateComplete;
    expect(wrapper.open).toBe(true);
    handle.close();
    await (wrapper as unknown as { updateComplete: Promise<void> }).updateComplete;
    expect(wrapper.open).toBe(false);
  });

  it('removes the entire <mk-modal> element from the DOM when the mk-close event fires', async () => {
    const { openModal } = await import('./modal-controller');
    vi.spyOn(HTMLDialogElement.prototype, 'showModal').mockImplementation(() => {});
    vi.spyOn(HTMLDialogElement.prototype, 'close').mockImplementation(() => {});
    openModal('hello');
    const wrapper = document.body.querySelector('mk-modal')!;
    expect(document.body.contains(wrapper)).toBe(true);
    // happy-dom does not auto-fire 'close' event on dialog.close(), so dispatch manually
    const dialog = wrapper.querySelector('dialog')!;
    dialog.dispatchEvent(new Event('close'));
    await new Promise((r) => setTimeout(r, 0));
    await (wrapper as unknown as { updateComplete: Promise<void> }).updateComplete;
    expect(document.body.contains(wrapper)).toBe(false);
  });

  it('stops emitting the console.warn stub message from openModal()', async () => {
    const warnSpy = vi.spyOn(console, 'warn').mockImplementation(() => {});
    const { openModal } = await import('./modal-controller');
    openModal('hello');
    const stubCalls = warnSpy.mock.calls.filter((args) =>
      String(args[0]).includes('openModal() stub'),
    );
    expect(stubCalls).toHaveLength(0);
  });
});
