// @vitest-environment happy-dom
import { describe, it, expect, vi, beforeEach } from 'vitest';
import type { ToastOptions, ModalOptions, ModalHandle, DrawerOptions, DrawerHandle, DrawerPlacement } from './index';

describe('theme-blank index', () => {
  it('exports a showToast function', async () => {
    const module = await import('./index');
    expect(typeof module.showToast).toBe('function');
  });

  it('exports an openModal function', async () => {
    const module = await import('./index');
    expect(typeof module.openModal).toBe('function');
  });

  it('exports ToastOptions type with optional variant and duration fields', () => {
    const opts: ToastOptions = { variant: 'info', duration: 3000 };
    expect(opts.variant).toBe('info');
    expect(opts.duration).toBe(3000);

    const optsPartial: ToastOptions = {};
    expect(optsPartial.variant).toBeUndefined();
    expect(optsPartial.duration).toBeUndefined();
  });

  it('exports ModalOptions type with optional dismissible and size fields', () => {
    const opts: ModalOptions = { dismissible: true, size: 'md' };
    expect(opts.dismissible).toBe(true);
    expect(opts.size).toBe('md');

    const optsPartial: ModalOptions = {};
    expect(optsPartial.dismissible).toBeUndefined();
    expect(optsPartial.size).toBeUndefined();
  });

  it('exports ModalHandle interface with a close method', () => {
    const handle: ModalHandle = { close: () => {} };
    expect(typeof handle.close).toBe('function');
  });

  describe('showToast', () => {
    beforeEach(() => {
      vi.restoreAllMocks();
      document.body.innerHTML = '';
    });

    it('showToast() delegates to toast-controller and creates a toast in the region', async () => {
      const { showToast } = await import('./index');
      showToast('hello');
      expect(document.querySelector('ol.mk-toast-region')).not.toBeNull();
      expect(document.querySelector('mk-toast')).not.toBeNull();
    });

    it('showToast() returns undefined', async () => {
      const { showToast } = await import('./index');
      const result = showToast('hello');
      expect(result).toBeUndefined();
    });
  });

  describe('openModal', () => {
    beforeEach(() => {
      vi.restoreAllMocks();
      document.body.innerHTML = '';
    });

    it('openModal() delegates to modal-controller and appends an mk-modal element', async () => {
      const { openModal } = await import('./index');
      openModal('some content');
      expect(document.body.querySelector('mk-modal')).not.toBeNull();
    });

    it('openModal() returns an object with a callable close method', async () => {
      const { openModal } = await import('./index');
      const handle = openModal('some content');
      expect(handle).toBeDefined();
      expect(typeof handle.close).toBe('function');
    });

    it('openModal() close method returns void', async () => {
      const { openModal } = await import('./index');
      const handle = openModal('some content');
      const result = handle.close();
      expect(result).toBeUndefined();
    });
  });

  it('exports DrawerOptions, DrawerHandle, DrawerPlacement type aliases from index.ts', () => {
    const opts: DrawerOptions = { size: 'sm', placement: 'left', dismissible: true };
    expect(opts.size).toBe('sm');
    expect(opts.placement).toBe('left');
    expect(opts.dismissible).toBe(true);

    const handle: DrawerHandle = { close: () => {} };
    expect(typeof handle.close).toBe('function');

    const placement: DrawerPlacement = 'right';
    expect(placement).toBe('right');
  });

  it('exports an openDrawer function', async () => {
    const module = await import('./index');
    expect(typeof module.openDrawer).toBe('function');
  });

  it('the entry file does not call defineAllComponents()', async () => {
    const fs = await import('node:fs');
    const path = await import('node:path');
    const { fileURLToPath } = await import('node:url');
    const __dirname = path.dirname(fileURLToPath(import.meta.url));
    const indexPath = path.join(__dirname, 'index.ts');
    const content = fs.readFileSync(indexPath, 'utf-8');
    expect(content).not.toContain('defineAllComponents');
  });
});
