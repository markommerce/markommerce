import { property } from 'lit/decorators.js';
import type { PropertyValues, ComplexAttributeConverter } from 'lit';
import { MkElement, registerBase } from '@markommerce/frontend';
import '../../css/components/mk-sidebar.css';

const stringOrUndefined: ComplexAttributeConverter<string | undefined> = {
  fromAttribute: (value: string | null): string | undefined =>
    value === null ? undefined : value,
  toAttribute: (value: string | undefined): string | null =>
    value === undefined ? null : value,
};

export class MkSidebarElement extends MkElement {
  @property({ converter: stringOrUndefined, reflect: true }) side?: 'left' | 'right';
  @property({ converter: stringOrUndefined, reflect: true }) width?: string;

  override connectedCallback(): void {
    const attrValue = this.getAttribute('width');
    if (attrValue !== null && attrValue !== '') {
      this.style.setProperty('--mk-sidebar-width', attrValue);
    }
    super.connectedCallback();
  }

  override updated(changed: PropertyValues<this>): void {
    super.updated(changed);
    if (changed.has('width')) {
      if (this.width) {
        this.style.setProperty('--mk-sidebar-width', this.width);
      } else {
        this.style.removeProperty('--mk-sidebar-width');
      }
    }
  }
}

registerBase('mk-sidebar', MkSidebarElement);
