import type { ToastOptions } from './index';

const MAX_VISIBLE = 5;

function getOrCreateRegion(): Element {
  const existing = document.querySelector('ol.mk-toast-region');
  if (existing) {
    return existing;
  }

  const region = document.createElement('ol');
  region.className = 'mk-toast-region';
  region.setAttribute('role', 'region');
  region.setAttribute('aria-live', 'polite');
  region.setAttribute('aria-label', 'Notifications');
  document.body.appendChild(region);
  return region;
}

export function showToast(message: string, options?: ToastOptions): void {
  const region = getOrCreateRegion();

  if (region.querySelectorAll('li').length >= MAX_VISIBLE) {
    const oldest = region.querySelector('li');
    if (oldest) {
      oldest.remove();
    }
  }

  const toast = document.createElement('mk-toast');
  toast.textContent = message;

  if (options?.variant !== undefined) {
    toast.setAttribute('variant', options.variant);
  }

  if (options?.duration !== undefined) {
    toast.setAttribute('duration', String(options.duration));
  }

  const li = document.createElement('li');

  const originalRemove = toast.remove.bind(toast);
  toast.remove = () => {
    originalRemove();
    li.remove();
  };

  li.appendChild(toast);
  region.appendChild(li);
}
