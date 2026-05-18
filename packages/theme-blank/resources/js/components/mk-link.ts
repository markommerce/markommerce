import { property } from 'lit/decorators.js';
import type { ComplexAttributeConverter } from 'lit';
import { MkElement, registerBase } from '@markommerce/frontend';
import '../../css/components/mk-link.css';

const stringOrUndefined: ComplexAttributeConverter<string | undefined> = {
  fromAttribute: (value: string | null): string | undefined =>
    value === null ? undefined : value,
  toAttribute: (value: string | undefined): string | null =>
    value === undefined ? null : value,
};

export class MkLinkElement extends MkElement {
  @property({ converter: stringOrUndefined, reflect: true }) variant?: 'default' | 'muted' | 'danger';
  @property({ converter: stringOrUndefined, reflect: true }) underline?: 'always' | 'hover' | 'never';
}

registerBase('mk-link', MkLinkElement);
