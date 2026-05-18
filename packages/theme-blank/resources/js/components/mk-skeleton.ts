import { property } from 'lit/decorators.js';
import { MkElement, registerBase } from '@markommerce/frontend';
import '../../css/components/mk-skeleton.css';

const optionalString = {
  fromAttribute: (value: string | null): string | undefined => value ?? undefined,
  toAttribute: (value: string | undefined): string | null => value ?? null,
};

export class MkSkeletonElement extends MkElement {
  @property({ converter: optionalString, reflect: true }) variant?: 'text' | 'circle' | 'rect';

  override connectedCallback(): void {
    super.connectedCallback();
    if (!this.hasAttribute('aria-hidden')) {
      this.setAttribute('aria-hidden', 'true');
    }
  }
}

registerBase('mk-skeleton', MkSkeletonElement);
