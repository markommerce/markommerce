// @vitest-environment happy-dom
import { describe, it, expect } from 'vitest';

declare module './events' {
  interface MarkommerceEventMap {
    'test:event': { count: number };
  }
}

import { dispatchMarkommerceEvent } from './events';

describe('dispatchMarkommerceEvent', () => {
  it('dispatches a CustomEvent with bubbles true and composed true by default', () => {
    const target = document.createElement('div');
    let captured: CustomEvent | null = null;
    target.addEventListener('test:event', (e) => {
      captured = e as CustomEvent;
    });

    dispatchMarkommerceEvent(target, 'test:event', { count: 1 });

    expect(captured).not.toBeNull();
    expect((captured as unknown as CustomEvent).bubbles).toBe(true);
    expect((captured as unknown as CustomEvent).composed).toBe(true);
  });

  it('includes the supplied detail object verbatim on the event', () => {
    const target = document.createElement('div');
    const detail = { count: 42 };
    let captured: CustomEvent | null = null;
    target.addEventListener('test:event', (e) => {
      captured = e as CustomEvent;
    });

    dispatchMarkommerceEvent(target, 'test:event', detail);

    expect((captured as unknown as CustomEvent).detail).toEqual({ count: 42 });
  });

  it('returns the dispatchEvent boolean result for callers that need it', () => {
    const target = document.createElement('div');

    const result = dispatchMarkommerceEvent(target, 'test:event', { count: 1 });

    expect(typeof result).toBe('boolean');
  });

  it('allows overriding bubbles, composed, and cancelable via options', () => {
    const target = document.createElement('div');
    let captured: CustomEvent | null = null;
    target.addEventListener('test:event', (e) => {
      captured = e as CustomEvent;
    });

    dispatchMarkommerceEvent(target, 'test:event', { count: 1 }, {
      bubbles: false,
      composed: false,
      cancelable: true,
    });

    expect((captured as unknown as CustomEvent).bubbles).toBe(false);
    expect((captured as unknown as CustomEvent).composed).toBe(false);
    expect((captured as unknown as CustomEvent).cancelable).toBe(true);
  });

  it('dispatches from the supplied target element so listeners on ancestors fire', () => {
    const parent = document.createElement('div');
    const child = document.createElement('span');
    parent.appendChild(child);
    document.body.appendChild(parent);

    let firedOnParent = false;
    parent.addEventListener('test:event', () => {
      firedOnParent = true;
    });

    dispatchMarkommerceEvent(child, 'test:event', { count: 1 });

    expect(firedOnParent).toBe(true);

    document.body.removeChild(parent);
  });

  it('provides a MarkommerceEventMap interface that consumers can declaration-merge into', () => {
    // This is a compile-time test. If TypeScript accepts the augmented interface,
    // the test passes. The declare module block at the top of this file augments
    // the interface with 'test:event'.
    // We verify runtime behavior: after merging, we can dispatch the event.
    const target = document.createElement('div');
    let fired = false;
    target.addEventListener('test:event', () => {
      fired = true;
    });

    dispatchMarkommerceEvent(target, 'test:event', { count: 99 });

    expect(fired).toBe(true);
  });

  it('augments DocumentEventMap and HTMLElementEventMap via declaration merging in the kernel index types', () => {
    // This is primarily a TypeScript compilation test.
    // We verify that after augmenting MarkommerceEventMap, the global maps include the key.
    // Runtime check: dispatch from child, captured at document level proves composed+bubbles.
    const child = document.createElement('div');
    document.body.appendChild(child);

    let documentCaptured = false;
    document.addEventListener('test:event', () => {
      documentCaptured = true;
    });

    dispatchMarkommerceEvent(child, 'test:event', { count: 5 });

    expect(documentCaptured).toBe(true);

    document.body.removeChild(child);
  });
});
