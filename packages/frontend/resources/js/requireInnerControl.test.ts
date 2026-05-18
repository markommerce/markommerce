// @vitest-environment happy-dom
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { requireInnerControl } from './requireInnerControl';

describe('requireInnerControl', () => {
  it('is exported from @markommerce/frontend index', async () => {
    const mod = await import('./index');
    expect(typeof mod.requireInnerControl).toBe('function');
  });

  it('returns the matching child element when the selector finds a descendant', () => {
    const parent = document.createElement('div');
    const child = document.createElement('input');
    parent.appendChild(child);

    const result = requireInnerControl(parent, 'input');
    expect(result).toBe(child);
  });

  it('returns null when no descendant matches the selector', () => {
    const parent = document.createElement('div');

    const result = requireInnerControl(parent, 'input');
    expect(result).toBeNull();
  });

  it('calls console.warn exactly once when no matching descendant exists', () => {
    const warnSpy = vi.spyOn(console, 'warn').mockImplementation(() => {});
    const parent = document.createElement('div');

    requireInnerControl(parent, 'input');

    expect(warnSpy).toHaveBeenCalledTimes(1);
    warnSpy.mockRestore();
  });

  it('does not call console.warn on a second call for the same element instance', () => {
    const warnSpy = vi.spyOn(console, 'warn').mockImplementation(() => {});
    const parent = document.createElement('div');

    requireInnerControl(parent, 'input');
    requireInnerControl(parent, 'input');

    expect(warnSpy).toHaveBeenCalledTimes(1);
    warnSpy.mockRestore();
  });

  it('calls console.warn again for a different element instance with no matching descendant', () => {
    const warnSpy = vi.spyOn(console, 'warn').mockImplementation(() => {});
    const parent1 = document.createElement('div');
    const parent2 = document.createElement('div');

    requireInnerControl(parent1, 'input');
    requireInnerControl(parent2, 'input');

    expect(warnSpy).toHaveBeenCalledTimes(2);
    warnSpy.mockRestore();
  });

  it('does not call console.warn when a matching descendant is present', () => {
    const warnSpy = vi.spyOn(console, 'warn').mockImplementation(() => {});
    const parent = document.createElement('div');
    parent.appendChild(document.createElement('input'));

    requireInnerControl(parent, 'input');

    expect(warnSpy).not.toHaveBeenCalled();
    warnSpy.mockRestore();
  });

  it('includes the element tag name in the warning message', () => {
    const warnSpy = vi.spyOn(console, 'warn').mockImplementation(() => {});
    const parent = document.createElement('mk-input');

    requireInnerControl(parent, 'input');

    expect(warnSpy).toHaveBeenCalledWith(expect.stringContaining('mk-input'));
    warnSpy.mockRestore();
  });

  it('includes the selector string in the warning message', () => {
    const warnSpy = vi.spyOn(console, 'warn').mockImplementation(() => {});
    const parent = document.createElement('div');

    requireInnerControl(parent, 'input[type="text"]');

    expect(warnSpy).toHaveBeenCalledWith(expect.stringContaining('input[type="text"]'));
    warnSpy.mockRestore();
  });
});
