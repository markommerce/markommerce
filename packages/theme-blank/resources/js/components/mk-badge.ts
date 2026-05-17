import { property } from 'lit/decorators.js';
import { MkElement, registerBase } from '@markommerce/frontend';
import '../../css/components/mk-badge.css';

const optionalString = {
  fromAttribute: (value: string | null): string | undefined => value ?? undefined,
  toAttribute: (value: string | undefined): string | null => value ?? null,
};

export class MkBadgeElement extends MkElement {
  @property({ converter: optionalString, reflect: true }) variant?: 'neutral' | 'primary' | 'success' | 'warning' | 'danger' | 'info';
  @property({ converter: optionalString, reflect: true }) size?: 'sm' | 'base' | 'lg';
}

registerBase('mk-badge', MkBadgeElement);
