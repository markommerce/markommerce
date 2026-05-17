import { property } from 'lit/decorators.js';
import type { ComplexAttributeConverter } from 'lit';
import { MkElement, registerBase } from '@markommerce/frontend';
import '../../css/components/mk-container.css';

const stringOrUndefined: ComplexAttributeConverter<string | undefined> = {
  fromAttribute: (value: string | null): string | undefined =>
    value === null ? undefined : value,
  toAttribute: (value: string | undefined): string | null =>
    value === undefined ? null : value,
};

export class MkContainerElement extends MkElement {
  @property({ converter: stringOrUndefined, reflect: true }) size?: 'sm' | 'md' | 'lg' | 'xl' | 'full';
}

registerBase('mk-container', MkContainerElement);
