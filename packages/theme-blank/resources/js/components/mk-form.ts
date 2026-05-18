import { MkElement, registerBase, requireInnerControl } from '@markommerce/frontend';
import type { MkFieldElement } from './mk-field';
import '../../css/components/mk-form.css';

export class MkFormElement extends MkElement {
  #form: HTMLFormElement | null = null;
  #submitting = false;

  override connectedCallback(): void {
    this.#form = requireInnerControl(this, 'form') as HTMLFormElement | null;
    if (this.#form) {
      this.#form.setAttribute('novalidate', '');
      this.#form.addEventListener('submit', this.#onSubmit);
    }
    super.connectedCallback();
  }

  override disconnectedCallback(): void {
    this.#form?.removeEventListener('submit', this.#onSubmit);
    super.disconnectedCallback();
  }

  #onSubmit = async (event: SubmitEvent): Promise<void> => {
    event.preventDefault();
    if (this.#submitting) return;
    this.#submitting = true;
    try {
      const fields = [...this.querySelectorAll('mk-field')] as MkFieldElement[];
      const results = await Promise.all(fields.map((f) => f.validate()));
      const invalidFields = fields.filter((_, i) => !results[i]);
      if (invalidFields.length > 0) {
        invalidFields[0]!.querySelector<HTMLElement>('input, textarea, select')?.focus();
        this.dispatchEvent(new CustomEvent('mk-invalid', {
          bubbles: true,
          composed: true,
          detail: { fields: invalidFields },
        }));
        return;
      }
      const formData = new FormData(this.#form!);
      this.dispatchEvent(new CustomEvent('mk-submit', {
        bubbles: true,
        composed: true,
        detail: { formData },
      }));
    } finally {
      this.#submitting = false;
    }
  };
}

registerBase('mk-form', MkFormElement);
