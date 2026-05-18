import { property } from 'lit/decorators.js';
import { MkElement, registerBase } from '@markommerce/frontend';
import '../../css/components/mk-stack.css';

export class MkStackElement extends MkElement {
  @property({ type: String, reflect: true }) gap?: string;
  @property({ type: String, reflect: true }) align?: 'start' | 'center' | 'end' | 'stretch';
}

registerBase('mk-stack', MkStackElement);
