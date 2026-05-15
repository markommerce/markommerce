import { describe, it, expect, beforeEach } from 'vitest';
import { registerHook, runHook, Hooks, resetHooksForTesting } from './hooks';

// Declaration merging to add test hooks
declare module './hooks' {
  interface HookRegistry {
    'test:transform': { payload: string; return: string };
    'test:numeric': { payload: number; return: number };
    'test:object': { payload: { value: number }; return: { value: number } };
  }
}

beforeEach(() => {
  resetHooksForTesting();
});

describe('hooks', () => {
  it('registers a hook handler and runs it via runHook', async () => {
    registerHook('test:transform', (payload) => payload + '-transformed');
    const result = await runHook('test:transform', 'hello');
    expect(result).toBe('hello-transformed');
  });

  it('threads the payload through multiple handlers in priority order', async () => {
    registerHook('test:transform', (payload) => payload + '-first', { priority: 10 });
    registerHook('test:transform', (payload) => payload + '-second', { priority: 20 });
    const result = await runHook('test:transform', 'hello');
    expect(result).toBe('hello-first-second');
  });

  it('returns the unmodified payload when no handlers are registered for the hook name', async () => {
    const result = await runHook('test:transform', 'hello');
    expect(result).toBe('hello');
  });

  it('defaults handler priority to 100 when not specified', async () => {
    registerHook('test:transform', (payload) => payload + '-default');
    registerHook('test:transform', (payload) => payload + '-low', { priority: 200 });
    registerHook('test:transform', (payload) => payload + '-high', { priority: 50 });
    const result = await runHook('test:transform', 'hello');
    expect(result).toBe('hello-high-default-low');
  });

  it('sorts handlers stably so equal priorities keep registration order', async () => {
    registerHook('test:transform', (payload) => payload + '-A', { priority: 100 });
    registerHook('test:transform', (payload) => payload + '-B', { priority: 100 });
    registerHook('test:transform', (payload) => payload + '-C', { priority: 100 });
    const result = await runHook('test:transform', 'hello');
    expect(result).toBe('hello-A-B-C');
  });

  it('awaits async handlers and chains their resolved values to the next handler', async () => {
    registerHook('test:transform', async (payload) => {
      return new Promise<string>((resolve) => {
        setTimeout(() => resolve(payload + '-async'), 10);
      });
    }, { priority: 10 });
    registerHook('test:transform', (payload) => payload + '-sync', { priority: 20 });
    const result = await runHook('test:transform', 'hello');
    expect(result).toBe('hello-async-sync');
  });

  it('allows a sync handler in a chain of async handlers without breaking the threading', async () => {
    registerHook('test:transform', (payload) => payload + '-sync1', { priority: 10 });
    registerHook('test:transform', async (payload) => payload + '-async', { priority: 20 });
    registerHook('test:transform', (payload) => payload + '-sync2', { priority: 30 });
    const result = await runHook('test:transform', 'hello');
    expect(result).toBe('hello-sync1-async-sync2');
  });

  it('surfaces handler exceptions via the returned Promise rejection', async () => {
    registerHook('test:transform', () => {
      throw new Error('handler error');
    });
    await expect(runHook('test:transform', 'hello')).rejects.toThrow('handler error');
  });

  it('exposes the Hooks namespace with register and run aliases', async () => {
    Hooks.register('test:transform', (payload) => payload + '-via-namespace');
    const result = await Hooks.run('test:transform', 'hello');
    expect(result).toBe('hello-via-namespace');
  });
});
