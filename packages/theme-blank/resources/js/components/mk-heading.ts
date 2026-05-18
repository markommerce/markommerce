import { property } from 'lit/decorators.js';
import { MkElement, registerBase } from '@markommerce/frontend';
import '../../css/components/mk-heading.css';

export class MkHeadingElement extends MkElement {
  @property({ type: String, reflect: true }) level?: '1' | '2' | '3' | '4' | '5' | '6';
  @property({ type: String, reflect: true }) size?: 'xs' | 'sm' | 'base' | 'lg' | 'xl' | '2xl' | '3xl';
  @property({ type: String, reflect: true }) weight?: 'normal' | 'medium' | 'bold';

  override connectedCallback(): void {
    const hasInnerHeading = Array.from(this.children).some((child) => /^h[1-6]$/i.test(child.tagName));
    if (!hasInnerHeading) {
      if (!this.hasAttribute('role')) {
        this.setAttribute('role', 'heading');
      }
      const levelAttr = this.getAttribute('level');
      if (levelAttr !== null && /^[1-6]$/.test(levelAttr) && !this.hasAttribute('aria-level')) {
        this.setAttribute('aria-level', levelAttr);
      }
    }
    super.connectedCallback();
  }
}

registerBase('mk-heading', MkHeadingElement);
