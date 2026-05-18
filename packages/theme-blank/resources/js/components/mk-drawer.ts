import { property } from 'lit/decorators.js';
import type { ComplexAttributeConverter } from 'lit';
import { MkElement, registerBase, requireInnerControl } from '@markommerce/frontend';
import '../../css/components/mk-drawer.css';

const stringOrUndefined: ComplexAttributeConverter<string | undefined> = {
  fromAttribute: (value: string | null): string | undefined =>
    value === null ? undefined : value,
  toAttribute: (value: string | undefined): string | null =>
    value === undefined ? null : value,
};

export class MkDrawerElement extends MkElement {
  @property({ type: Boolean, reflect: true }) open = false;
  @property({ converter: stringOrUndefined, reflect: true }) size?: 'sm' | 'md' | 'lg';
  @property({ converter: stringOrUndefined, reflect: true }) placement?: 'left' | 'right';
  @property({ type: Boolean, reflect: true }) dismissible = false;

  #reflectingClose = false;

  override connectedCallback(): void {
    if (!this.hasAttribute('placement')) {
      this.setAttribute('placement', 'right');
    }
    super.connectedCallback();
    requireInnerControl(this, 'dialog');
    const dialog = this.querySelector('dialog');
    if (dialog) {
      dialog.addEventListener('close', this.#onDialogClose);
      dialog.addEventListener('cancel', this.#onDialogCancel);
      dialog.addEventListener('click', this.#onDialogClick);
    }
  }

  override disconnectedCallback(): void {
    const dialog = this.querySelector('dialog');
    if (dialog) {
      dialog.removeEventListener('close', this.#onDialogClose);
      dialog.removeEventListener('cancel', this.#onDialogCancel);
      dialog.removeEventListener('click', this.#onDialogClick);
    }
    super.disconnectedCallback();
  }

  #onDialogCancel = (event: Event): void => {
    if (!this.dismissible) {
      event.preventDefault();
    }
  };

  #onDialogClick = (event: MouseEvent): void => {
    if (event.target === this.querySelector('dialog') && this.dismissible) {
      this.querySelector('dialog')?.close();
    }
  };

  #onDialogClose = (): void => {
    if (this.#reflectingClose) return;
    this.#reflectingClose = true;
    this.open = false;
    this.dispatchEvent(new CustomEvent('mk-close', { bubbles: true, composed: true }));
  };

  override updated(changed: Map<string, unknown>): void {
    if (changed.has('open')) {
      const dialog = this.querySelector('dialog');
      if (!dialog) return;
      if (this.open) {
        dialog.showModal();
      } else if (!this.#reflectingClose) {
        dialog.close();
      }
      this.#reflectingClose = false;
    }
  }
}

registerBase('mk-drawer', MkDrawerElement);
