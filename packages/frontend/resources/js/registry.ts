export type Constructor<T> = new (...args: never[]) => T;
export type Mixin<TBase extends Constructor<HTMLElement>> = (Base: TBase) => TBase;

export interface MixinDescriptor<TBase extends Constructor<HTMLElement>> {
  mixin: Mixin<TBase>;
  source: string;
  priority?: number;
}

export interface RegisteredComponent {
  tagName: string;
  base: Constructor<HTMLElement>;
  mixins: ReadonlyArray<MixinDescriptor<Constructor<HTMLElement>>>;
}

export class RegistryError extends Error {}

const registry = new Map<string, RegisteredComponent>();
let defined = false;

export function registerBase(tagName: string, BaseClass: Constructor<HTMLElement>): void {
  if (registry.has(tagName)) {
    throw new RegistryError(`Base class already registered for tag: ${tagName}`);
  }
  registry.set(tagName, { tagName, base: BaseClass, mixins: [] });
}

export function addMixin<TBase extends Constructor<HTMLElement>>(
  tagName: string,
  mixin: Mixin<TBase>,
  options: { source: string; priority?: number },
): void {
  const entry = registry.get(tagName);
  if (!entry) {
    throw new RegistryError(`No base class registered for tag: ${tagName}`);
  }
  const descriptor: MixinDescriptor<Constructor<HTMLElement>> = {
    mixin: mixin as Mixin<Constructor<HTMLElement>>,
    source: options.source,
    priority: options.priority,
  };
  (entry.mixins as Array<MixinDescriptor<Constructor<HTMLElement>>>).push(descriptor);
}

export function defineAllComponents(): void {
  if (defined) {
    return;
  }
  for (const entry of registry.values()) {
    const sorted = [...entry.mixins].sort((a, b) => {
      const pa = a.priority ?? 100;
      const pb = b.priority ?? 100;
      return pa - pb;
    });
    let Composed = entry.base;
    for (const descriptor of sorted) {
      Composed = descriptor.mixin(Composed);
    }
    customElements.define(entry.tagName, Composed);
  }
  defined = true;
}

export function getRegisteredComponents(): readonly RegisteredComponent[] {
  return [...registry.values()];
}

export function getMixinChain(tagName: string): readonly { source: string; priority: number }[] {
  const entry = registry.get(tagName);
  if (!entry) {
    return [];
  }
  const sorted = [...entry.mixins].sort((a, b) => {
    const pa = a.priority ?? 100;
    const pb = b.priority ?? 100;
    return pa - pb;
  });
  return sorted.map((d) => ({ source: d.source, priority: d.priority ?? 100 }));
}

export function _resetForTesting(): void {
  registry.clear();
  defined = false;
}
