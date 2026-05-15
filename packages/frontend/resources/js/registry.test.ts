// @vitest-environment happy-dom
import { beforeEach, describe, expect, it } from 'vitest';
import {
  _resetForTesting,
  addMixin,
  defineAllComponents,
  getMixinChain,
  getRegisteredComponents,
  registerBase,
  RegistryError,
} from './registry';

beforeEach(() => {
  _resetForTesting();
});

describe('component registry', () => {
  it('registers a base class with registerBase and exposes it via getRegisteredComponents', () => {
    class MyElement extends HTMLElement {}
    registerBase('my-element', MyElement);

    const components = getRegisteredComponents();
    expect(components).toHaveLength(1);
    expect(components[0]?.tagName).toBe('my-element');
    expect(components[0]?.base).toBe(MyElement);
    expect(components[0]?.mixins).toEqual([]);
  });

  it('throws RegistryError when registerBase is called twice for the same tag name', () => {
    class MyElement extends HTMLElement {}
    registerBase('my-element', MyElement);

    expect(() => registerBase('my-element', MyElement)).toThrow(RegistryError);
  });

  it('adds a mixin via addMixin and lists it via getMixinChain', () => {
    class MyElement extends HTMLElement {}
    registerBase('my-element', MyElement);

    const myMixin = (Base: Constructor<HTMLElement>) => class extends Base {};
    addMixin('my-element', myMixin, { source: '@markommerce/frontend-demo', priority: 50 });

    const chain = getMixinChain('my-element');
    expect(chain).toHaveLength(1);
    expect(chain[0]?.source).toBe('@markommerce/frontend-demo');
    expect(chain[0]?.priority).toBe(50);
  });

  it('throws RegistryError when addMixin is called for a tag with no registered base', () => {
    const myMixin = (Base: Constructor<HTMLElement>) => class extends Base {};

    expect(() =>
      addMixin('non-existent-element', myMixin, { source: '@markommerce/frontend-demo' }),
    ).toThrow(RegistryError);
  });

  it('applies a single mixin to a base class on defineAllComponents and the element registers in customElements', () => {
    class MyElement extends HTMLElement {}
    registerBase('my-mixin-element', MyElement);

    let mixinApplied = false;
    const myMixin = (Base: Constructor<HTMLElement>) => {
      mixinApplied = true;
      return class extends Base {};
    };
    addMixin('my-mixin-element', myMixin, { source: '@markommerce/frontend-demo' });

    defineAllComponents();

    expect(mixinApplied).toBe(true);
    expect(customElements.get('my-mixin-element')).toBeDefined();
  });

  it('applies multiple mixins in ascending priority order producing a deterministic composition', () => {
    class MyElement extends HTMLElement {}
    registerBase('priority-element', MyElement);

    const applicationOrder: number[] = [];

    const mixinA = (Base: Constructor<HTMLElement>) => {
      applicationOrder.push(200);
      return class extends Base {};
    };
    const mixinB = (Base: Constructor<HTMLElement>) => {
      applicationOrder.push(10);
      return class extends Base {};
    };
    const mixinC = (Base: Constructor<HTMLElement>) => {
      applicationOrder.push(50);
      return class extends Base {};
    };

    addMixin('priority-element', mixinA, { source: 'source-a', priority: 200 });
    addMixin('priority-element', mixinB, { source: 'source-b', priority: 10 });
    addMixin('priority-element', mixinC, { source: 'source-c', priority: 50 });

    defineAllComponents();

    // Ascending order: 10, 50, 200 (lower priority number applied first/innermost)
    expect(applicationOrder).toEqual([10, 50, 200]);
  });

  it('defaults mixin priority to 100 when not specified', () => {
    class MyElement extends HTMLElement {}
    registerBase('default-priority-element', MyElement);

    const myMixin = (Base: Constructor<HTMLElement>) => class extends Base {};
    addMixin('default-priority-element', myMixin, { source: '@markommerce/frontend-demo' });

    const chain = getMixinChain('default-priority-element');
    expect(chain).toHaveLength(1);
    expect(chain[0]?.priority).toBe(100);
  });

  it('records the source string of each mixin for getMixinChain output', () => {
    class MyElement extends HTMLElement {}
    registerBase('source-element', MyElement);

    const mixinA = (Base: Constructor<HTMLElement>) => class extends Base {};
    const mixinB = (Base: Constructor<HTMLElement>) => class extends Base {};

    addMixin('source-element', mixinA, { source: '@markommerce/frontend', priority: 10 });
    addMixin('source-element', mixinB, { source: '@markommerce/frontend-demo', priority: 20 });

    const chain = getMixinChain('source-element');
    expect(chain).toHaveLength(2);
    expect(chain[0]?.source).toBe('@markommerce/frontend');
    expect(chain[1]?.source).toBe('@markommerce/frontend-demo');
  });

  it('leaves customElements untouched until defineAllComponents is called', () => {
    class MyElement extends HTMLElement {}
    registerBase('lazy-element', MyElement);

    // Before defineAllComponents is called, the element should not be registered
    expect(customElements.get('lazy-element')).toBeUndefined();

    defineAllComponents();

    // After defineAllComponents is called, the element should be registered
    expect(customElements.get('lazy-element')).toBeDefined();
  });

  it('makes defineAllComponents idempotent — calling twice does not re-define elements', () => {
    class MyElement extends HTMLElement {}
    registerBase('idempotent-element', MyElement);

    let mixinCallCount = 0;
    const myMixin = (Base: Constructor<HTMLElement>) => {
      mixinCallCount++;
      return class extends Base {};
    };
    addMixin('idempotent-element', myMixin, { source: 'test-source' });

    defineAllComponents();
    defineAllComponents(); // second call — should be no-op

    expect(mixinCallCount).toBe(1);
    expect(customElements.get('idempotent-element')).toBeDefined();
  });

  it('composes mixins so a later mixin can call super to chain template methods', () => {
    const callOrder: string[] = [];

    class BaseElement extends HTMLElement {
      greet(): string {
        callOrder.push('base');
        return 'base';
      }
    }
    registerBase('chaining-element', BaseElement);

    // Priority 10: inner mixin — wraps base
    const innerMixin = (Base: Constructor<HTMLElement>) => {
      return class extends (Base as Constructor<BaseElement>) {
        override greet(): string {
          callOrder.push('inner');
          return `inner(${super.greet()})`;
        }
      } as unknown as Constructor<HTMLElement>;
    };

    // Priority 20: outer mixin — wraps inner
    const outerMixin = (Base: Constructor<HTMLElement>) => {
      return class extends (Base as Constructor<BaseElement>) {
        override greet(): string {
          callOrder.push('outer');
          return `outer(${super.greet()})`;
        }
      } as unknown as Constructor<HTMLElement>;
    };

    addMixin('chaining-element', innerMixin, { source: 'inner-source', priority: 10 });
    addMixin('chaining-element', outerMixin, { source: 'outer-source', priority: 20 });

    defineAllComponents();

    const ComposedClass = customElements.get('chaining-element') as typeof BaseElement | undefined;
    expect(ComposedClass).toBeDefined();
    const instance = new ComposedClass!();
    const result = instance.greet();

    // outer wraps inner wraps base; call order should be outer -> inner -> base
    expect(callOrder).toEqual(['outer', 'inner', 'base']);
    expect(result).toBe('outer(inner(base))');
  });
});
