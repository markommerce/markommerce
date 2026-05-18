import { property } from 'lit/decorators.js';
import type { ComplexAttributeConverter } from 'lit';
import { MkElement, registerBase } from '@markommerce/frontend';
import '../../css/components/mk-alert.css';

const stringOrUndefined: ComplexAttributeConverter<string | undefined> = {
  fromAttribute: (value: string | null): string | undefined =>
    value === null ? undefined : value,
  toAttribute: (value: string | undefined): string | null =>
    value === undefined ? null : value,
};

export class MkAlertElement extends MkElement {
  @property({ converter: stringOrUndefined, reflect: true }) variant?: 'info' | 'success' | 'warning' | 'danger';

  override connectedCallback(): void {
    super.connectedCallback();
    if (this.hasAttribute('dismissible') && !this.querySelector('button.mk-alert-close')) {
      const btn = document.createElement('button');
      btn.className = 'mk-alert-close';
      btn.setAttribute('aria-label', 'Dismiss');
      btn.addEventListener('click', () => this.remove());
      this.appendChild(btn);
    }
  }
}

registerBase('mk-alert', MkAlertElement);
