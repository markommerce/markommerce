import { property } from 'lit/decorators.js';
import type { ComplexAttributeConverter } from 'lit';
import { MkElement, registerBase } from '@markommerce/frontend';
import '../../css/components/mk-divider.css';

const stringOrUndefined: ComplexAttributeConverter<string | undefined> = {
  fromAttribute: (value: string | null): string | undefined =>
    value === null ? undefined : value,
  toAttribute: (value: string | undefined): string | null =>
    value === undefined ? null : value,
};

export class MkDividerElement extends MkElement {
  @property({ converter: stringOrUndefined, reflect: true }) orientation?: 'horizontal' | 'vertical';

  override connectedCallback(): void {
    super.connectedCallback();
    if (!this.hasAttribute('role')) {
      this.setAttribute('role', 'separator');
    }
    if (!this.hasAttribute('aria-orientation') && this.orientation === 'vertical') {
      this.setAttribute('aria-orientation', 'vertical');
    }
  }
}

registerBase('mk-divider', MkDividerElement);
