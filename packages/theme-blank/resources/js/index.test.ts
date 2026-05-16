// @vitest-environment happy-dom
import { describe, it, expect, vi, beforeEach } from 'vitest';
import type { ToastOptions, ModalOptions, ModalHandle } from './index';

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
    });

    it('showToast() emits a console.warn containing the Phase 4 stub message', async () => {
      const warnSpy = vi.spyOn(console, 'warn').mockImplementation(() => {});
      const { showToast } = await import('./index');
      showToast('hello');
      expect(warnSpy).toHaveBeenCalledOnce();
      expect(warnSpy.mock.calls[0]?.[0]).toContain(
        '[markommerce/theme-blank] showToast() stub — real implementation lands in Phase 4',
      );
    });

    it('showToast() returns undefined', async () => {
      vi.spyOn(console, 'warn').mockImplementation(() => {});
      const { showToast } = await import('./index');
      const result = showToast('hello');
      expect(result).toBeUndefined();
    });
  });

  describe('openModal', () => {
    beforeEach(() => {
      vi.restoreAllMocks();
    });

    it('openModal() emits a console.warn containing the Phase 4 stub message', async () => {
      const warnSpy = vi.spyOn(console, 'warn').mockImplementation(() => {});
      const { openModal } = await import('./index');
      openModal('some content');
      expect(warnSpy).toHaveBeenCalledOnce();
      expect(warnSpy.mock.calls[0]?.[0]).toContain(
        '[markommerce/theme-blank] openModal() stub — real implementation lands in Phase 4',
      );
    });

    it('openModal() returns an object with a callable close method', async () => {
      vi.spyOn(console, 'warn').mockImplementation(() => {});
      const { openModal } = await import('./index');
      const handle = openModal('some content');
      expect(handle).toBeDefined();
      expect(typeof handle.close).toBe('function');
    });

    it('openModal() close method returns void', async () => {
      vi.spyOn(console, 'warn').mockImplementation(() => {});
      const { openModal } = await import('./index');
      const handle = openModal('some content');
      const result = handle.close();
      expect(result).toBeUndefined();
    });
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
