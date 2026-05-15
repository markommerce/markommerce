// Empty in the kernel. Modules merge new keys via declaration merging.
// eslint-disable-next-line @typescript-eslint/no-empty-object-type
export interface MarkommerceEventMap {}

export interface DispatchOptions {
  bubbles?: boolean;
  composed?: boolean;
  cancelable?: boolean;
}

export function dispatchMarkommerceEvent<K extends string>(
  target: EventTarget,
  name: K,
  detail: K extends keyof MarkommerceEventMap ? MarkommerceEventMap[K] : unknown,
  options?: DispatchOptions,
): boolean {
  const event = new CustomEvent(name, {
    detail,
    bubbles: options?.bubbles ?? true,
    composed: options?.composed ?? true,
    cancelable: options?.cancelable ?? false,
  });
  return target.dispatchEvent(event);
}

declare global {
  // eslint-disable-next-line @typescript-eslint/no-empty-object-type
  interface DocumentEventMap extends MarkommerceEventMap {}
  // eslint-disable-next-line @typescript-eslint/no-empty-object-type
  interface HTMLElementEventMap extends MarkommerceEventMap {}
}
