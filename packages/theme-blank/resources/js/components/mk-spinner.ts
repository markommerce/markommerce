import { property } from 'lit/decorators.js';
import type { ComplexAttributeConverter } from 'lit';
import { MkElement, registerBase } from '@markommerce/frontend';
import '../../css/components/mk-spinner.css';

const stringOrUndefined: ComplexAttributeConverter<string | undefined> = {
  fromAttribute: (value: string | null): string | undefined =>
    value === null ? undefined : value,
  toAttribute: (value: string | undefined): string | null =>
    value === undefined ? null : value,
};

export class MkSpinnerElement extends MkElement {
  @property({ converter: stringOrUndefined, reflect: true }) size?: 'sm' | 'base' | 'lg';

  override connectedCallback(): void {
    super.connectedCallback();
    if (!this.hasAttribute('role')) {
      this.setAttribute('role', 'status');
    }
    if (!this.hasAttribute('aria-live')) {
      this.setAttribute('aria-live', 'polite');
    }
    const hasTextContent = Array.from(this.childNodes).some(
      (node) => node.nodeType === Node.TEXT_NODE && node.textContent?.trim(),
    );
    const hasVisuallyHidden = this.querySelector('span.mk-visually-hidden') !== null;
    if (!hasTextContent && !hasVisuallyHidden) {
      const span = document.createElement('span');
      span.className = 'mk-visually-hidden';
      span.textContent = 'Loading…';
      this.appendChild(span);
    }
  }
}

registerBase('mk-spinner', MkSpinnerElement);
