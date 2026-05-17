import { property } from 'lit/decorators.js';
import { MkElement, registerBase } from '@markommerce/frontend';
import '../../css/components/mk-text.css';

export class MkTextElement extends MkElement {
  @property({ type: String, reflect: true }) variant?: 'body' | 'lead' | 'small' | 'muted';
  @property({ type: String, reflect: true }) weight?: 'normal' | 'medium' | 'bold';
}

registerBase('mk-text', MkTextElement);
