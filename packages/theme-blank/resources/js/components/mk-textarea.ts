import { property } from 'lit/decorators.js';
import type { ComplexAttributeConverter } from 'lit';
import { MkElement, registerBase, requireInnerControl } from '@markommerce/frontend';
import '../../css/components/mk-textarea.css';

const stringOrUndefined: ComplexAttributeConverter<string | undefined> = {
  fromAttribute: (value: string | null): string | undefined =>
    value === null ? undefined : value,
  toAttribute: (value: string | undefined): string | null =>
    value === undefined ? null : value,
};

export class MkTextareaElement extends MkElement {
  @property({ converter: stringOrUndefined, reflect: true }) variant?: 'outline' | 'filled';
  @property({ converter: stringOrUndefined, reflect: true }) size?: 'sm' | 'base' | 'lg';

  override connectedCallback(): void {
    super.connectedCallback();
    requireInnerControl(this, 'textarea');
  }
}

registerBase('mk-textarea', MkTextareaElement);
