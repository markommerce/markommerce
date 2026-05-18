import { property } from 'lit/decorators.js';
import { MkElement, registerBase, requireInnerControl } from '@markommerce/frontend';
import '../../css/components/mk-checkbox.css';

const optionalString = {
  fromAttribute: (value: string | null): string | undefined => value ?? undefined,
  toAttribute: (value: string | undefined): string | null => value ?? null,
};

export class MkCheckboxElement extends MkElement {
  @property({ converter: optionalString, reflect: true }) size?: 'sm' | 'base' | 'lg';

  override connectedCallback(): void {
    super.connectedCallback();
    requireInnerControl(this, 'input[type="checkbox"]');
  }
}

registerBase('mk-checkbox', MkCheckboxElement);
