export interface DrawerHandle {
  close(): void;
}

export type DrawerPlacement = 'left' | 'right';

export interface DrawerOptions {
  size?: 'sm' | 'md' | 'lg';
  placement?: DrawerPlacement;
  dismissible?: boolean;
}

export function openDrawer(content: HTMLElement | string, options?: DrawerOptions): DrawerHandle {
  const wrapper = document.createElement('mk-drawer');
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

  wrapper.setAttribute('placement', options?.placement ?? 'right');

  if (options?.dismissible === true) {
    wrapper.setAttribute('dismissible', '');
  }

  document.body.appendChild(wrapper);

  wrapper.setAttribute('open', '');

  wrapper.addEventListener('mk-close', () => {
    wrapper.remove();
  });

  return {
    close(): void {
      if (wrapper.isConnected) {
        wrapper.removeAttribute('open');
      }
    },
  };
}
