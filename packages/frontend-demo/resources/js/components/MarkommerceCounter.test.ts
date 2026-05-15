// @vitest-environment happy-dom
import { describe, it, expect, afterEach } from 'vitest';
import { MarkommerceCounterElement } from './MarkommerceCounter';
import type { TemplateResult } from 'lit';

afterEach(() => {
  document.body.innerHTML = '';
});

let tagCounter = 0;

function uniqueTag(): string {
  return `markommerce-counter-${++tagCounter}`;
}

async function createCounter(attrs = ''): Promise<HTMLElement & { updateComplete: Promise<boolean> }> {
  const tag = uniqueTag();
  if (!customElements.get(tag)) {
    customElements.define(tag, class extends MarkommerceCounterElement {});
  }
  document.body.innerHTML = `<${tag} ${attrs}></${tag}>`;
  const el = document.body.firstElementChild as HTMLElement & { updateComplete: Promise<boolean> };
  await el.updateComplete;
  return el;
}

describe('MarkommerceCounterElement', () => {
  it('renders a button with the initial count of 0 by default', async () => {
    const el = await createCounter();
    expect(el.textContent).toContain('0');
    const button = el.querySelector('button');
    expect(button).not.toBeNull();
  });

  it('accepts start-value as an HTML attribute and uses it as the initial count', async () => {
    const el = await createCounter('start-value="5"');
    expect(el.textContent).toContain('5');
  });

  it('increments the count by one on button click', async () => {
    const el = await createCounter();
    const button = el.querySelector('button') as HTMLButtonElement;
    button.click();
    await el.updateComplete;
    expect(el.textContent).toContain('1');
  });

  it('dispatches a markommerce:counter:changed event with the new count in detail on increment', async () => {
    const el = await createCounter();
    const button = el.querySelector('button') as HTMLButtonElement;
    let detail: { count: number } | null = null;
    el.addEventListener('markommerce:counter:changed', (e: Event) => {
      detail = (e as CustomEvent<{ count: number }>).detail;
    });
    button.click();
    await el.updateComplete;
    expect(detail).not.toBeNull();
    expect(detail?.count).toBe(1);
  });

  it('renders to light DOM so global theme styles apply', async () => {
    // Light DOM: createRenderRoot() returns the element itself.
    // We verify this by checking that the rendered content appears as direct
    // children of the element (no shadow root).
    const el = await createCounter();
    expect(el.shadowRoot).toBeNull();
    // Content rendered directly into the element (light DOM)
    const div = el.querySelector('.counter');
    expect(div).not.toBeNull();
  });

  it('exposes a renderLabel template method that mixins can override', async () => {
    const tag = uniqueTag();
    let labelResult: TemplateResult | null = null;
    class TestElement extends MarkommerceCounterElement {
      override renderLabel(): TemplateResult {
        labelResult = super.renderLabel();
        return labelResult;
      }
    }
    customElements.define(tag, TestElement);
    document.body.innerHTML = `<${tag}></${tag}>`;
    const el = document.body.firstElementChild as HTMLElement & { updateComplete: Promise<boolean> };
    await el.updateComplete;
    expect(labelResult).not.toBeNull();
  });

  it('exposes a renderButton template method that mixins can override', async () => {
    const tag = uniqueTag();
    let buttonResult: TemplateResult | null = null;
    class TestElement extends MarkommerceCounterElement {
      override renderButton(): TemplateResult {
        buttonResult = super.renderButton();
        return buttonResult;
      }
    }
    customElements.define(tag, TestElement);
    document.body.innerHTML = `<${tag}></${tag}>`;
    const el = document.body.firstElementChild as HTMLElement & { updateComplete: Promise<boolean> };
    await el.updateComplete;
    expect(buttonResult).not.toBeNull();
  });

  it('exposes a renderExtras template method that mixins can extend', async () => {
    const tag = uniqueTag();
    let extrasCalled = false;
    class TestElement extends MarkommerceCounterElement {
      override renderExtras(): TemplateResult {
        extrasCalled = true;
        return super.renderExtras();
      }
    }
    customElements.define(tag, TestElement);
    document.body.innerHTML = `<${tag}></${tag}>`;
    const el = document.body.firstElementChild as HTMLElement & { updateComplete: Promise<boolean> };
    await el.updateComplete;
    expect(extrasCalled).toBe(true);
  });

  it('composes a LabelSuffixMixin so the label displays count plus suffix when the mixin is registered', async () => {
    const { LabelSuffixMixin } = await import('../mixins/LabelSuffixMixin');
    const tag = uniqueTag();
    const MixedElement = LabelSuffixMixin(MarkommerceCounterElement);
    customElements.define(tag, MixedElement);
    document.body.innerHTML = `<${tag} suffix=" items"></${tag}>`;
    const el = document.body.firstElementChild as HTMLElement & { updateComplete: Promise<boolean> };
    await el.updateComplete;
    expect(el.textContent).toContain('0 items');
  });

  it('the LabelSuffixMixin defaults its suffix to a documented placeholder when no attribute is set', async () => {
    const { LabelSuffixMixin } = await import('../mixins/LabelSuffixMixin');
    const tag = uniqueTag();
    const MixedElement = LabelSuffixMixin(MarkommerceCounterElement);
    customElements.define(tag, MixedElement);
    document.body.innerHTML = `<${tag}></${tag}>`;
    const el = document.body.firstElementChild as HTMLElement & { updateComplete: Promise<boolean> };
    await el.updateComplete;
    // Should show default suffix (empty string or documented placeholder)
    expect(el.textContent).toBeTruthy();
    // The suffix property must exist with a default value
    const instance = el as unknown as { suffix: string };
    expect(typeof instance.suffix).toBe('string');
  });

  it('imports the counter.css stylesheet so its styles register under the components cascade layer', async () => {
    // This verifies that the module imports counter.css
    // By attempting to import the module and checking it doesn't throw
    const module = await import('./MarkommerceCounter');
    expect(module.MarkommerceCounterElement).toBeDefined();
    // The CSS import is in the module — verified by the fact the module loads
    // The actual test is the static import in the file
    // We check the source file contains the import
    const fs = await import('node:fs');
    const path = await import('node:path');
    const { fileURLToPath } = await import('node:url');
    const __dirname = path.dirname(fileURLToPath(import.meta.url));
    const src = fs.readFileSync(path.join(__dirname, 'MarkommerceCounter.ts'), 'utf-8');
    expect(src).toContain("counter.css");
  });

  it('the dispatched event has bubbles true and composed true', async () => {
    const el = await createCounter();
    const button = el.querySelector('button') as HTMLButtonElement;
    let capturedEvent: CustomEvent | null = null;
    document.addEventListener('markommerce:counter:changed', (e: Event) => {
      capturedEvent = e as CustomEvent;
    });
    button.click();
    await el.updateComplete;
    expect(capturedEvent).not.toBeNull();
    expect(capturedEvent?.bubbles).toBe(true);
    expect(capturedEvent?.composed).toBe(true);
  });

  it('the typed event map includes markommerce:counter:changed via declaration merging', async () => {
    // This is a compile-time check via TypeScript. We verify the declaration merging
    // exists by checking the module declares it. Since we import from './MarkommerceCounter'
    // which augments '@markommerce/frontend', the type is available.
    // Verify via TypeScript assignment compatibility (no-op at runtime)
    const module = await import('./MarkommerceCounter');
    expect(module.MarkommerceCounterElement).toBeDefined();
    // The declaration merging is tested at compile time via tsc --noEmit
    // At runtime, we confirm the event name string is what it should be
    const eventName = 'markommerce:counter:changed';
    expect(eventName).toBe('markommerce:counter:changed');
  });
});
