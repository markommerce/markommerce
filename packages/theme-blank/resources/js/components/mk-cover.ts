import { property } from 'lit/decorators.js';
import type { PropertyValues, ComplexAttributeConverter } from 'lit';
import { MkElement, registerBase } from '@markommerce/frontend';
import '../../css/components/mk-cover.css';

const stringOrUndefined: ComplexAttributeConverter<string | undefined> = {
  fromAttribute: (value: string | null): string | undefined =>
    value === null ? undefined : value,
  toAttribute: (value: string | undefined): string | null =>
    value === undefined ? null : value,
};

export class MkCoverElement extends MkElement {
  @property({ converter: stringOrUndefined, reflect: true, attribute: 'min-height' }) minHeight?: string;

  override connectedCallback(): void {
    const attrValue = this.getAttribute('min-height');
    if (attrValue !== null && attrValue !== '') {
      this.style.setProperty('--mk-cover-min-height', attrValue);
    }
    super.connectedCallback();
  }

  override updated(changed: PropertyValues<this>): void {
    super.updated(changed);
    if (changed.has('minHeight')) {
      if (this.minHeight) {
        this.style.setProperty('--mk-cover-min-height', this.minHeight);
      } else {
        this.style.removeProperty('--mk-cover-min-height');
      }
    }
  }
}

registerBase('mk-cover', MkCoverElement);
