import { property } from 'lit/decorators.js';
import type { PropertyValues, ComplexAttributeConverter } from 'lit';
import { MkElement, registerBase } from '@markommerce/frontend';
import '../../css/components/mk-switcher.css';

const stringOrUndefined: ComplexAttributeConverter<string | undefined> = {
  fromAttribute: (value: string | null): string | undefined =>
    value === null ? undefined : value,
  toAttribute: (value: string | undefined): string | null =>
    value === undefined ? null : value,
};

const numberOrUndefined: ComplexAttributeConverter<number | undefined> = {
  fromAttribute: (value: string | null): number | undefined =>
    value === null ? undefined : Number(value),
  toAttribute: (value: number | undefined): string | null =>
    value === undefined ? null : String(value),
};

export class MkSwitcherElement extends MkElement {
  @property({ converter: stringOrUndefined, reflect: true }) threshold?: string;
  @property({ converter: stringOrUndefined, reflect: true }) gap?: string;
  @property({ converter: numberOrUndefined, reflect: true }) limit?: number;

  override connectedCallback(): void {
    const attrValue = this.getAttribute('threshold');
    if (attrValue !== null && attrValue !== '') {
      this.style.setProperty('--mk-switcher-threshold', attrValue);
    }
    super.connectedCallback();
  }

  override updated(changed: PropertyValues<this>): void {
    super.updated(changed);
    if (changed.has('threshold')) {
      if (this.threshold) {
        this.style.setProperty('--mk-switcher-threshold', this.threshold);
      } else {
        this.style.removeProperty('--mk-switcher-threshold');
      }
    }
  }
}

registerBase('mk-switcher', MkSwitcherElement);
