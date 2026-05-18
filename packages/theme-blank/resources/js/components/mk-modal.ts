import { property } from 'lit/decorators.js';
import { MkElement, registerBase, requireInnerControl } from '@markommerce/frontend';
import '../../css/components/mk-modal.css';

const optionalString = {
  fromAttribute: (value: string | null): string | undefined => value ?? undefined,
  toAttribute: (value: string | undefined): string | null => value ?? null,
};

export class MkModalElement extends MkElement {
  @property({ type: Boolean, reflect: true }) open = false;
  @property({ converter: optionalString, reflect: true }) size?: 'sm' | 'md' | 'lg';
  @property({ type: Boolean, reflect: true }) dismissible = false;

  #reflectingClose = false;

  override connectedCallback(): void {
    super.connectedCallback();
    const dialog = requireInnerControl(this, 'dialog') as HTMLDialogElement | null;
    if (!dialog) return;
    dialog.addEventListener('close', async () => {
      this.#reflectingClose = true;
      this.open = false;
      await this.updateComplete;
      this.#reflectingClose = false;
      this.dispatchEvent(new CustomEvent('mk-close', { bubbles: true, composed: true }));
    });
    dialog.addEventListener('click', (e) => {
      if (e.target === dialog && this.dismissible) {
        dialog.close();
      }
    });
    dialog.addEventListener('cancel', (e) => {
      if (!this.dismissible) {
        e.preventDefault();
      }
    });
  }

  override updated(changed: Map<string, unknown>): void {
    if (changed.has('open')) {
      const dialog = this.querySelector('dialog');
      if (!dialog) return;
      if (this.open) {
        dialog.showModal();
      } else if (!this.#reflectingClose && changed.get('open') === true) {
        dialog.close();
      }
    }
  }
}

registerBase('mk-modal', MkModalElement);
