import { property } from 'lit/decorators.js';
import type { ComplexAttributeConverter } from 'lit';
import { MkElement, registerBase, requireInnerControl } from '@markommerce/frontend';
import '../../css/components/mk-switch.css';

const stringOrUndefined: ComplexAttributeConverter<string | undefined> = {
  fromAttribute: (value: string | null): string | undefined =>
    value === null ? undefined : value,
  toAttribute: (value: string | undefined): string | null =>
    value === undefined ? null : value,
};

export class MkSwitchElement extends MkElement {
  @property({ converter: stringOrUndefined, reflect: true }) size?: 'sm' | 'base' | 'lg';

  override connectedCallback(): void {
    super.connectedCallback();
    const input = requireInnerControl(this, 'input[type="checkbox"]');
    if (input instanceof HTMLInputElement && !input.hasAttribute('role')) {
      input.setAttribute('role', 'switch');
    }
  }
}

registerBase('mk-switch', MkSwitchElement);
