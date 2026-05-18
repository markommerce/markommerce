import { property } from 'lit/decorators.js';
import { registerBase, MkElement } from '@markommerce/frontend';
import '../../css/components/mk-cluster.css';

export class MkClusterElement extends MkElement {
  @property({ type: String, reflect: true }) gap?: string;
  @property({ type: String, reflect: true }) align?: string;
  @property({ type: String, reflect: true }) justify?: string;
}

registerBase('mk-cluster', MkClusterElement);
