import { LitElement, html, nothing } from 'lit';
import { property, state } from 'lit/decorators.js';
import { dispatchMarkommerceEvent } from '@markommerce/frontend';
import type { TemplateResult } from 'lit';
import '../../css/components/counter.css';

declare module '@markommerce/frontend' {
  interface MarkommerceEventMap {
    'markommerce:counter:changed': { count: number };
  }
}

export class MarkommerceCounterElement extends LitElement {
  @property({ type: Number, attribute: 'start-value' }) startValue = 0;

  @state() protected count = 0;

  override createRenderRoot(): HTMLElement {
    return this;
  }

  override connectedCallback(): void {
    super.connectedCallback();
    this.count = this.startValue;
  }

  protected increment(): void {
    this.count += 1;
    dispatchMarkommerceEvent(this, 'markommerce:counter:changed', { count: this.count });
  }

  protected renderLabel(): TemplateResult {
    return html`<span class="counter__label">${this.count}</span>`;
  }

  protected renderButton(): TemplateResult {
    return html`<button class="counter__button" @click=${this.increment}>Increment</button>`;
  }

  protected renderExtras(): TemplateResult | typeof nothing {
    return nothing;
  }

  override render(): TemplateResult {
    return html`<div class="counter">${this.renderLabel()}${this.renderButton()}${this.renderExtras()}</div>`;
  }
}
