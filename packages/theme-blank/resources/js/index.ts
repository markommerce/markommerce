// Cascade-layer baseline — must come first.
import '@markommerce/frontend/css/layers.css';
// Open Props raw tokens (unlayered).
import 'open-props/style.css';
// Theme-blank semantic tokens (inside @layer tokens).
import '../css/tokens.css';
// Base styles reset (inside @layer base).
import '../css/base.css';
// Page layout structures (inside @layer theme).
import '../css/layouts.css';

import './components';
import { showToast as _showToast } from './toast-controller';
import { openDrawer as _openDrawer } from './drawer-controller';
import { openModal as _openModal } from './modal-controller';

export type { DrawerOptions, DrawerHandle, DrawerPlacement } from './drawer-controller';
import type { DrawerOptions, DrawerHandle } from './drawer-controller';

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
  _showToast(message, options);
}

export interface ModalHandle {
  close(): void;
}

export function openModal(content: HTMLElement | string, options?: ModalOptions): ModalHandle {
  return _openModal(content, options);
}

export function openDrawer(content: HTMLElement | string, options?: DrawerOptions): DrawerHandle {
  return _openDrawer(content, options);
}
