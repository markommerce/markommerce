import { MkElement, registerBase } from '@markommerce/frontend';
import '../../css/components/mk-field.css';

type SyncValidator = (value: string, form: FormData | null) => string | null;
type AsyncValidator = (value: string, form: FormData | null) => Promise<string | null>;

export class MkFieldElement extends MkElement {
  readonly #syncValidators = new Map<string, SyncValidator>();
  readonly #asyncValidators = new Map<string, AsyncValidator>();
  #debounceTimer: ReturnType<typeof setTimeout> | undefined;
  #control: HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement | null = null;

  addValidator(name: string, fn: SyncValidator): void {
    this.#syncValidators.set(name, fn);
  }

  addAsyncValidator(name: string, fn: AsyncValidator): void {
    this.#asyncValidators.set(name, fn);
  }

  async validate(): Promise<boolean> {
    if (!this.#control) return true;
    clearTimeout(this.#debounceTimer);
    // 1. Clear stale custom message
    this.#control.setCustomValidity('');
    // 2. Run sync validators
    const syncMsg = this.#runSyncValidators();
    if (syncMsg !== null) {
      this.#control.setCustomValidity(syncMsg);
    }
    // 3. If still valid, run async validators
    if (this.#control.validity.valid && this.#asyncValidators.size > 0) {
      const value = this.#control.value;
      const form = this.#control.form ? new FormData(this.#control.form) : null;
      const msgs = await Promise.all([...this.#asyncValidators.values()].map((fn) => fn(value, form)));
      const firstMsg = msgs.find((m) => m !== null) ?? null;
      if (firstMsg !== null) {
        this.#control.setCustomValidity(firstMsg);
      }
    }
    // 4. Call checkValidity() to dispatch invalid event AND populate validationMessage for Layer A
    this.#control.checkValidity();
    // 5. Surface whatever message is now set
    this.#setError(this.#control.validationMessage || null);
    return this.#control.validity.valid;
  }

  override connectedCallback(): void {
    this.#control = this.querySelector('input, textarea, select') as
      | HTMLInputElement
      | HTMLTextAreaElement
      | HTMLSelectElement
      | null;
    this.dataset['state'] = 'pristine';
    this.#control?.addEventListener('blur', this.#onBlur);
    this.#control?.addEventListener('input', this.#onInput);
    this.#control?.addEventListener('invalid', this.#onInvalid);
    super.connectedCallback();
  }

  override disconnectedCallback(): void {
    this.#control?.removeEventListener('blur', this.#onBlur);
    this.#control?.removeEventListener('input', this.#onInput);
    this.#control?.removeEventListener('invalid', this.#onInvalid);
    clearTimeout(this.#debounceTimer);
    super.disconnectedCallback();
  }

  #onBlur = async (): Promise<void> => {
    this.dataset['touched'] = '';
    const syncMsg = this.#runSyncValidators();
    if (syncMsg !== null) {
      this.#control!.setCustomValidity(syncMsg);
      this.#setError(syncMsg);
    } else {
      this.#control!.setCustomValidity('');
      this.#setError(this.#control!.validationMessage || null);
    }
    this.#runAsyncValidators();
  };

  #onInput = (): void => {
    if (this.dataset['state'] === 'invalid') {
      this.#control!.setCustomValidity('');
      const errEl = this.querySelector('[data-mk-error]');
      if (errEl) errEl.textContent = '';
      this.dataset['state'] = 'pristine';
    }
  };

  #onInvalid = (): void => {
    if (!this.#control) return;
    this.#setError(this.#control.validationMessage || null);
  };

  #runSyncValidators(): string | null {
    if (!this.#control) return null;
    const value = this.#control.value;
    const form = this.#control.form ? new FormData(this.#control.form) : null;
    for (const fn of this.#syncValidators.values()) {
      const msg = fn(value, form);
      if (msg !== null) return msg;
    }
    return null;
  }

  #runAsyncValidators(): void {
    if (!this.#control || this.#asyncValidators.size === 0) return;
    clearTimeout(this.#debounceTimer);
    this.#debounceTimer = setTimeout(async () => {
      if (!this.#control) return;
      this.dataset['state'] = 'validating';
      const value = this.#control.value;
      const form = this.#control.form ? new FormData(this.#control.form) : null;
      const msgs = await Promise.all([...this.#asyncValidators.values()].map((fn) => fn(value, form)));
      const firstMsg = msgs.find((m) => m !== null) ?? null;
      if (firstMsg !== null) {
        this.#control.setCustomValidity(firstMsg);
      } else {
        this.#control.setCustomValidity('');
      }
      this.#setError(this.#control.validationMessage || null);
    }, 300);
  }

  #setError(message: string | null): void {
    const errEl = this.querySelector('[data-mk-error]');
    if (errEl) errEl.textContent = message ?? '';
    const isInvalid = !this.#control?.validity.valid;
    if (message || isInvalid) {
      this.dataset['state'] = 'invalid';
    } else {
      this.dataset['state'] = this.#control?.validity.valid ? 'valid' : 'pristine';
    }
  }
}

registerBase('mk-field', MkFieldElement);
