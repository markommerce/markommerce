import type { ModalOptions, ModalHandle } from './index';

export function openModal(content: HTMLElement | string, options?: ModalOptions): ModalHandle {
  const wrapper = document.createElement('mk-modal');
  const dialog = document.createElement('dialog');

  if (typeof content === 'string') {
    dialog.innerHTML = content;
  } else {
    dialog.appendChild(content);
  }

  wrapper.appendChild(dialog);

  if (options?.size) {
    wrapper.setAttribute('size', options.size);
  }

  if (options?.dismissible === true) {
    wrapper.setAttribute('dismissible', '');
  }

  document.body.appendChild(wrapper);

  (wrapper as unknown as { open: boolean }).open = true;

  wrapper.addEventListener('mk-close', () => {
    wrapper.remove();
  });

  return {
    close(): void {
      if (!wrapper.isConnected) return;
      (wrapper as unknown as { open: boolean }).open = false;
    },
  };
}
