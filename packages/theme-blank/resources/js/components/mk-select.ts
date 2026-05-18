import { property } from 'lit/decorators.js';
import { MkElement, registerBase, requireInnerControl } from '@markommerce/frontend';
import '../../css/components/mk-select.css';

const optionalString = {
  fromAttribute: (value: string | null): string | undefined => value ?? undefined,
  toAttribute: (value: string | undefined): string | null => value ?? null,
};

export class MkSelectElement extends MkElement {
  @property({ converter: optionalString, reflect: true }) variant?: 'outline' | 'filled';
  @property({ converter: optionalString, reflect: true }) size?: 'sm' | 'base' | 'lg';

  override connectedCallback(): void {
    super.connectedCallback();
    requireInnerControl(this, 'select');
  }
}

registerBase('mk-select', MkSelectElement);
