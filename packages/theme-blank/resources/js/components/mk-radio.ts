import { property } from 'lit/decorators.js';
import { MkElement, registerBase, requireInnerControl } from '@markommerce/frontend';
import '../../css/components/mk-radio.css';

const optionalString = {
  fromAttribute: (value: string | null): string | undefined => value ?? undefined,
  toAttribute: (value: string | undefined): string | null => value ?? null,
};

export class MkRadioElement extends MkElement {
  @property({ converter: optionalString, reflect: true }) size?: 'sm' | 'base' | 'lg';

  override connectedCallback(): void {
    super.connectedCallback();
    requireInnerControl(this, 'input[type="radio"]');
  }
}

registerBase('mk-radio', MkRadioElement);
