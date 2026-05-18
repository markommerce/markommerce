import { property } from 'lit/decorators.js';
import { MkElement, registerBase, requireInnerControl } from '@markommerce/frontend';
import '../../css/components/mk-button.css';

const optionalString = {
  fromAttribute: (value: string | null): string | undefined => value ?? undefined,
  toAttribute: (value: string | undefined): string | null => value ?? null,
};

export class MkButtonElement extends MkElement {
  @property({ converter: optionalString, reflect: true }) variant?: 'primary' | 'secondary' | 'ghost' | 'danger';
  @property({ converter: optionalString, reflect: true }) size?: 'sm' | 'base' | 'lg';
  @property({ type: Boolean, reflect: true }) loading = false;

  override connectedCallback(): void {
    super.connectedCallback();
    requireInnerControl(this, 'button');
    this.#syncLoading();
  }

  override updated(changed: Map<string, unknown>): void {
    if (changed.has('loading')) this.#syncLoading();
  }

  #syncLoading(): void {
    const btn = this.querySelector('button');
    if (!btn) return;
    if (this.loading) {
      btn.disabled = true;
      this.setAttribute('aria-busy', 'true');
    } else {
      btn.disabled = false;
      this.removeAttribute('aria-busy');
    }
  }
}

registerBase('mk-button', MkButtonElement);
