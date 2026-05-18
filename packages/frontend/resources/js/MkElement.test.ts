// @vitest-environment happy-dom
import { describe, it, expect } from 'vitest';
import { LitElement, html, nothing } from 'lit';
import type { TemplateResult } from 'lit';

describe('MkElement', () => {
  it('exports MkElement from @markommerce/frontend', async () => {
    const mod = await import('@markommerce/frontend');
    expect(mod.MkElement).toBeDefined();
  });

  it('MkElement extends LitElement', async () => {
    const { MkElement } = await import('./MkElement');
    expect(Object.getPrototypeOf(MkElement)).toBe(LitElement);
  });

  it('MkElement createRenderRoot returns the element itself (light DOM)', async () => {
    const { MkElement } = await import('./MkElement');
    customElements.define('mk-element-render-root-test', MkElement);
    const el = document.createElement('mk-element-render-root-test') as InstanceType<typeof MkElement>;
    document.body.appendChild(el);
    // createRenderRoot returns `this` (the element itself), not a shadow root
    expect(el.renderRoot).toBe(el);
    document.body.removeChild(el);
  });

  it('MkElement default render returns the nothing sentinel', async () => {
    const { MkElement } = await import('./MkElement');
    // Access the prototype render method directly to check return value
    const el = Object.create(MkElement.prototype) as InstanceType<typeof MkElement>;
    const result = el.render();
    expect(result).toBe(nothing);
  });

  it('defining a subclass tag and appending an instance to the DOM with existing light-DOM children does not remove those children after Lit first update (await updateComplete then assert children preserved)', async () => {
    const { MkElement } = await import('./MkElement');

    class MkElementTest1 extends MkElement {}
    customElements.define('mk-element-test-1', MkElementTest1);

    const container = document.createElement('div');
    container.innerHTML = '<mk-element-test-1><span>preserved</span></mk-element-test-1>';
    document.body.appendChild(container);

    const el = container.querySelector('mk-element-test-1') as MkElementTest1;
    await el.updateComplete;

    expect(el.querySelector('span')).not.toBeNull();
    expect(el.textContent).toContain('preserved');

    document.body.removeChild(container);
  });

  it('subclasses that override render() can return a TemplateResult without TypeScript override errors and the rendered template is inserted before the existing children, not in place of them', async () => {
    const { MkElement } = await import('./MkElement');

    class MkElementTest2 extends MkElement {
      override render(): TemplateResult {
        return html`<button>click me</button>`;
      }
    }
    customElements.define('mk-element-test-2', MkElementTest2);

    const container = document.createElement('div');
    container.innerHTML = '<mk-element-test-2><span>preserved</span></mk-element-test-2>';
    document.body.appendChild(container);

    const el = container.querySelector('mk-element-test-2') as MkElementTest2;
    await el.updateComplete;

    // Both the rendered template and the existing child should be present
    expect(el.querySelector('button')).not.toBeNull();
    expect(el.querySelector('span')).not.toBeNull();
    expect(el.textContent).toContain('preserved');

    // The rendered content (button) should appear before the existing light-DOM child (span)
    const button = el.querySelector('button') as HTMLButtonElement;
    const span = el.querySelector('span') as HTMLSpanElement;
    expect(
      button.compareDocumentPosition(span) & Node.DOCUMENT_POSITION_FOLLOWING,
    ).toBeTruthy();

    document.body.removeChild(container);
  });
});
