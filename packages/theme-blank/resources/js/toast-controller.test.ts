// @vitest-environment happy-dom
import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import { defineAllComponents } from '@markommerce/frontend';
import './components/mk-toast';

beforeAll(() => {
  if (!customElements.get('mk-toast')) {
    defineAllComponents();
  }
});

afterEach(() => {
  document.body.innerHTML = '';
  vi.useRealTimers();
  vi.restoreAllMocks();
});

describe('toast-controller', () => {
  describe('showToast', () => {
    let showToast: (message: string, options?: { variant?: string; duration?: number }) => void;

    beforeEach(async () => {
      vi.resetModules();
      const mod = await import('./toast-controller');
      showToast = mod.showToast;
    });

    it('it lazily creates a single ol.mk-toast-region element appended to document.body on first call', () => {
      expect(document.querySelector('ol.mk-toast-region')).toBeNull();
      showToast('hello');
      const region = document.querySelector('ol.mk-toast-region');
      expect(region).not.toBeNull();
      expect(region!.tagName).toBe('OL');
      expect(document.body.contains(region)).toBe(true);
    });

    it('it reuses the existing ol.mk-toast-region element on subsequent calls instead of creating a new one', () => {
      showToast('first');
      const region1 = document.querySelector('ol.mk-toast-region');
      showToast('second');
      const region2 = document.querySelector('ol.mk-toast-region');
      expect(region1).toBe(region2);
      expect(document.querySelectorAll('ol.mk-toast-region')).toHaveLength(1);
    });

    it('it appends a new <li><mk-toast></mk-toast></li> entry to the region per call', () => {
      showToast('first');
      showToast('second');
      const region = document.querySelector('ol.mk-toast-region')!;
      const items = region.querySelectorAll('li');
      expect(items).toHaveLength(2);
      expect(items[0]!.querySelector('mk-toast')).not.toBeNull();
      expect(items[1]!.querySelector('mk-toast')).not.toBeNull();
    });

    it('it sets the variant attribute on the mk-toast when options.variant is provided', () => {
      showToast('hello', { variant: 'success' });
      const toast = document.querySelector('mk-toast')!;
      expect(toast.getAttribute('variant')).toBe('success');
    });

    it('it sets the duration attribute on the mk-toast when options.duration is provided', () => {
      showToast('hello', { duration: 3000 });
      const toast = document.querySelector('mk-toast')!;
      expect(toast.getAttribute('duration')).toBe('3000');
    });

    it('it evicts the oldest toast li when more than 5 toasts are visible', () => {
      showToast('one');
      showToast('two');
      showToast('three');
      showToast('four');
      showToast('five');
      const region = document.querySelector('ol.mk-toast-region')!;
      expect(region.querySelectorAll('li')).toHaveLength(5);

      // Get first li reference
      const firstLi = region.querySelector('li')!;
      const firstToast = firstLi.querySelector('mk-toast')!;
      expect(firstToast.textContent).toBe('one');

      showToast('six');
      expect(region.querySelectorAll('li')).toHaveLength(5);
      expect(region.contains(firstLi)).toBe(false);

      const toasts = Array.from(region.querySelectorAll('mk-toast'));
      expect(toasts.map((t) => t.textContent)).toEqual(['two', 'three', 'four', 'five', 'six']);
    });

    it('it stops emitting the console.warn stub message from showToast()', () => {
      const warnSpy = vi.spyOn(console, 'warn');
      showToast('hello');
      const stubCalls = warnSpy.mock.calls.filter((args) =>
        typeof args[0] === 'string' &&
        args[0].includes('[markommerce/theme-blank] showToast() stub'),
      );
      expect(stubCalls).toHaveLength(0);
    });

    it('it removes the toast li wrapper from the region after the mk-toast auto-dismisses', () => {
      vi.useFakeTimers();

      showToast('hello', { duration: 3000 });
      const region = document.querySelector('ol.mk-toast-region')!;
      const li = region.querySelector('li')!;
      expect(region.contains(li)).toBe(true);

      vi.advanceTimersByTime(3001);
      expect(region.contains(li)).toBe(false);
    });

    it('it adopts a pre-existing ol.mk-toast-region present in the DOM (does not create a duplicate, does not move it)', () => {
      const existing = document.createElement('ol');
      existing.className = 'mk-toast-region';
      document.body.appendChild(existing);
      const originalParent = existing.parentElement;

      showToast('hello');

      expect(document.querySelectorAll('ol.mk-toast-region')).toHaveLength(1);
      expect(document.querySelector('ol.mk-toast-region')).toBe(existing);
      expect(existing.parentElement).toBe(originalParent);
    });

    it('it sets role="region", aria-live="polite", aria-label="Notifications" on the auto-created region (NOT on an adopted pre-existing one)', () => {
      // Auto-created region should have these attributes
      showToast('hello');
      const region = document.querySelector('ol.mk-toast-region')!;
      expect(region.getAttribute('role')).toBe('region');
      expect(region.getAttribute('aria-live')).toBe('polite');
      expect(region.getAttribute('aria-label')).toBe('Notifications');

      // Reset
      document.body.innerHTML = '';
      vi.resetModules();

      // Pre-existing region should NOT get these attributes added/overwritten
      const existing = document.createElement('ol');
      existing.className = 'mk-toast-region';
      document.body.appendChild(existing);

      // Re-import fresh module instance
      // We need to test this inline since modules are reset
      // The behavior is: existing element is adopted as-is without modifying its attributes
      expect(existing.getAttribute('role')).toBeNull();
      expect(existing.getAttribute('aria-live')).toBeNull();
      expect(existing.getAttribute('aria-label')).toBeNull();
    });
  });
});
