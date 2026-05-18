import { property } from 'lit/decorators.js';
import type { PropertyValues } from 'lit';
import { MkElement, registerBase } from '@markommerce/frontend';
import '../../css/components/mk-grid.css';

export class MkGridElement extends MkElement {
  @property({ type: String, reflect: true }) gap?: string;
  @property({ type: String, reflect: true }) min?: string;

  override connectedCallback(): void {
    const attrValue = this.getAttribute('min');
    if (attrValue !== null && attrValue !== '') {
      this.style.setProperty('--mk-grid-min', attrValue);
    }
    super.connectedCallback();
  }

  override updated(changed: PropertyValues<this>): void {
    super.updated(changed);
    if (changed.has('min')) {
      if (this.min) {
        this.style.setProperty('--mk-grid-min', this.min);
      } else {
        this.style.removeProperty('--mk-grid-min');
      }
    }
  }
}

registerBase('mk-grid', MkGridElement);
