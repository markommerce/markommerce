import { property } from 'lit/decorators.js';
import type { ComplexAttributeConverter } from 'lit';
import { MkElement, registerBase } from '@markommerce/frontend';
import '../../css/components/mk-toast.css';

const stringOrUndefined: ComplexAttributeConverter<string | undefined> = {
  fromAttribute: (value: string | null): string | undefined =>
    value === null ? undefined : value,
  toAttribute: (value: string | undefined): string | null =>
    value === undefined ? null : value,
};

export class MkToastElement extends MkElement {
  @property({ converter: stringOrUndefined, reflect: true }) variant?: 'info' | 'success' | 'warning' | 'danger';
  @property({ type: Number, reflect: true }) duration = 5000;

  #dismissTimer: ReturnType<typeof setTimeout> | undefined;

  override connectedCallback(): void {
    super.connectedCallback();
    if (!this.hasAttribute('role')) {
      this.setAttribute('role', 'status');
    }
    if (!this.hasAttribute('tabindex')) {
      this.setAttribute('tabindex', '0');
    }
    if (this.hasAttribute('dismissible') && !this.querySelector('button.mk-toast-close')) {
      const btn = document.createElement('button');
      btn.className = 'mk-toast-close';
      btn.setAttribute('aria-label', 'Dismiss');
      btn.addEventListener('click', () => this.remove());
      this.appendChild(btn);
    }
    if (this.duration > 0 && isFinite(this.duration)) {
      this.#dismissTimer = setTimeout(() => this.remove(), this.duration);
    }
  }

  override disconnectedCallback(): void {
    super.disconnectedCallback();
    clearTimeout(this.#dismissTimer);
    this.#dismissTimer = undefined;
  }
}

registerBase('mk-toast', MkToastElement);
