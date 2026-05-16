export type ToastVariant = 'info' | 'success' | 'warning' | 'danger';

export interface ToastOptions {
  variant?: ToastVariant;
  duration?: number;
}

export type ModalSize = 'sm' | 'md' | 'lg';

export interface ModalOptions {
  dismissible?: boolean;
  size?: ModalSize;
}

export function showToast(message: string, options?: ToastOptions): void {
  console.warn(
    '[markommerce/theme-blank] showToast() stub — real implementation lands in Phase 4',
    { message, options },
  );
}

export interface ModalHandle {
  close(): void;
}

export function openModal(content: HTMLElement | string, options?: ModalOptions): ModalHandle {
  console.warn(
    '[markommerce/theme-blank] openModal() stub — real implementation lands in Phase 4',
    { content, options },
  );
  return { close: () => {} };
}
