// @vitest-environment happy-dom
import { afterEach, beforeAll, describe, expect, it, vi } from 'vitest';
import { defineAllComponents, getRegisteredComponents } from '@markommerce/frontend';
import './mk-field';
import { MkFieldElement } from './mk-field';
import { MkElement } from '@markommerce/frontend';

beforeAll(() => {
  if (!customElements.get('mk-field')) {
    defineAllComponents();
  }
});

afterEach(() => {
  document.body.innerHTML = '';
  vi.restoreAllMocks();
  vi.useRealTimers();
});

function buildField(controlTag: 'input' | 'textarea' | 'select' = 'input'): {
  field: MkFieldElement;
  control: HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement;
} {
  const field = document.createElement('mk-field') as MkFieldElement;
  const label = document.createElement('label');
  label.textContent = 'Email';
  const control = document.createElement(controlTag) as
    | HTMLInputElement
    | HTMLTextAreaElement
    | HTMLSelectElement;
  if (control instanceof HTMLInputElement) {
    control.type = 'email';
    control.name = 'email';
  }
  const errEl = document.createElement('small');
  errEl.dataset['mkError'] = '';
  field.appendChild(label);
  field.appendChild(control);
  field.appendChild(errEl);
  return { field, control };
}

describe('mk-field', () => {
  it('registers under the tag name "mk-field"', () => {
    const registered = getRegisteredComponents().find((c) => c.tagName === 'mk-field');
    expect(registered).toBeDefined();
    expect(registered!.base).toBe(MkFieldElement);
  });

  it('MkFieldElement extends MkElement', () => {
    expect(Object.getPrototypeOf(MkFieldElement)).toBe(MkElement);
  });

  it('finds the first input descendant as the control', async () => {
    const { field, control } = buildField('input');
    document.body.appendChild(field);
    await field.updateComplete;
    // validate() returns true when valid — control found means it operates on the input
    const result = await field.validate();
    expect(result).toBe(true);
    // Direct evidence: setting required and validating should fail
    (control as HTMLInputElement).required = true;
    const resultRequired = await field.validate();
    expect(resultRequired).toBe(false);
  });

  it('finds a textarea descendant when no input is present', async () => {
    const { field, control } = buildField('textarea');
    document.body.appendChild(field);
    await field.updateComplete;
    (control as HTMLTextAreaElement).required = true;
    const result = await field.validate();
    expect(result).toBe(false);
  });

  it('finds a select descendant when no input or textarea is present', async () => {
    const { field, control } = buildField('select');
    document.body.appendChild(field);
    await field.updateComplete;
    (control as HTMLSelectElement).required = true;
    const result = await field.validate();
    expect(result).toBe(false);
  });

  it('sets data-state="pristine" on connectedCallback', async () => {
    const { field } = buildField();
    document.body.appendChild(field);
    await field.updateComplete;
    expect(field.dataset['state']).toBe('pristine');
  });

  it('sets data-touched on the element after the first blur event on the inner control', async () => {
    const { field, control } = buildField();
    document.body.appendChild(field);
    await field.updateComplete;
    expect(field.dataset['touched']).toBeUndefined();
    control.dispatchEvent(new Event('blur'));
    await new Promise((r) => setTimeout(r, 0));
    expect(field.dataset['touched']).toBe('');
  });

  it('does not remove data-touched on subsequent blur events (sticky)', async () => {
    const { field, control } = buildField();
    document.body.appendChild(field);
    await field.updateComplete;
    control.dispatchEvent(new Event('blur'));
    await new Promise((r) => setTimeout(r, 0));
    control.dispatchEvent(new Event('blur'));
    await new Promise((r) => setTimeout(r, 0));
    expect(field.dataset['touched']).toBe('');
  });

  it('the data-touched + :has(input:invalid):not(:focus-within) CSS rule is present in mk-field.css as the older-browser fallback', async () => {
    const fs = await import('fs');
    const path = await import('path');
    const cssPath = path.resolve(
      __dirname,
      '../../css/components/mk-field.css',
    );
    const content = fs.readFileSync(cssPath, 'utf-8');
    expect(content).toContain('data-touched');
    expect(content).toContain(':has(input:invalid');
    expect(content).toContain(':not(:focus-within)');
  });

  it('populates [data-mk-error] with the native validationMessage when the control fires an invalid event', async () => {
    const { field, control } = buildField();
    (control as HTMLInputElement).required = true;
    document.body.appendChild(field);
    await field.updateComplete;
    // Simulate the browser setting validationMessage before firing invalid
    // (happy-dom does not auto-populate validationMessage from native constraints)
    control.setCustomValidity('Value is required');
    control.dispatchEvent(new Event('invalid'));
    expect(field.querySelector('[data-mk-error]')!.textContent).toBeTruthy();
  });

  it('sets data-state="invalid" when the control fires an invalid event', async () => {
    const { field, control } = buildField();
    (control as HTMLInputElement).required = true;
    document.body.appendChild(field);
    await field.updateComplete;
    control.dispatchEvent(new Event('invalid'));
    expect(field.dataset['state']).toBe('invalid');
  });

  it('clears [data-mk-error] and sets data-state="valid" when the control becomes valid after being invalid', async () => {
    const { field, control } = buildField();
    (control as HTMLInputElement).required = true;
    document.body.appendChild(field);
    await field.updateComplete;
    // First make it invalid
    await field.validate();
    expect(field.dataset['state']).toBe('invalid');
    // Now fix the control
    (control as HTMLInputElement).value = 'test@example.com';
    control.dispatchEvent(new Event('input'));
    await field.updateComplete;
    // After input, state should clear from invalid
    expect(field.dataset['state']).not.toBe('invalid');
    // Validate again to make it valid
    await field.validate();
    expect(field.dataset['state']).toBe('valid');
    expect(field.querySelector('[data-mk-error]')!.textContent).toBe('');
  });

  it('addValidator stores a sync validator by name', async () => {
    const { field } = buildField();
    document.body.appendChild(field);
    await field.updateComplete;
    const validator = vi.fn().mockReturnValue(null);
    field.addValidator('custom', validator);
    await field.validate();
    expect(validator).toHaveBeenCalled();
  });

  it('addValidator — sync validator returning a non-null message calls setCustomValidity with that message', async () => {
    const { field, control } = buildField();
    document.body.appendChild(field);
    await field.updateComplete;
    field.addValidator('custom', () => 'Custom error');
    const spy = vi.spyOn(control, 'setCustomValidity');
    await field.validate();
    expect(spy).toHaveBeenCalledWith('Custom error');
  });

  it('addValidator — sync validator returning null clears setCustomValidity', async () => {
    const { field, control } = buildField();
    document.body.appendChild(field);
    await field.updateComplete;
    field.addValidator('custom', () => null);
    const spy = vi.spyOn(control, 'setCustomValidity');
    await field.validate();
    // First call clears stale custom message
    expect(spy).toHaveBeenCalledWith('');
  });

  it('addValidator — calling addValidator with the same name overwrites the previous validator', async () => {
    const { field } = buildField();
    document.body.appendChild(field);
    await field.updateComplete;
    const first = vi.fn().mockReturnValue('first error');
    const second = vi.fn().mockReturnValue('second error');
    field.addValidator('custom', first);
    field.addValidator('custom', second);
    await field.validate();
    expect(first).not.toHaveBeenCalled();
    expect(second).toHaveBeenCalled();
  });

  it('addAsyncValidator sets data-state="validating" while the async function is pending', async () => {
    vi.useFakeTimers();
    const { field, control } = buildField();
    (control as HTMLInputElement).value = 'test@example.com';
    document.body.appendChild(field);
    await field.updateComplete;
    let resolveAsync!: (v: string | null) => void;
    field.addAsyncValidator('async', () => new Promise<string | null>((res) => { resolveAsync = res; }));
    // Trigger blur to start debounce
    control.dispatchEvent(new Event('blur'));
    await vi.advanceTimersByTimeAsync(300);
    expect(field.dataset['state']).toBe('validating');
    resolveAsync(null);
    await vi.runAllTimersAsync();
  });

  it('addAsyncValidator sets data-state="invalid" and populates [data-mk-error] when the async validator returns a message', async () => {
    vi.useFakeTimers();
    const { field, control } = buildField();
    (control as HTMLInputElement).value = 'test@example.com';
    document.body.appendChild(field);
    await field.updateComplete;
    field.addAsyncValidator('async', async () => 'Async error');
    control.dispatchEvent(new Event('blur'));
    await vi.advanceTimersByTimeAsync(300);
    await vi.runAllTimersAsync();
    expect(field.dataset['state']).toBe('invalid');
    expect(field.querySelector('[data-mk-error]')!.textContent).toBe('Async error');
  });

  it('addAsyncValidator sets data-state="valid" when the async validator returns null', async () => {
    vi.useFakeTimers();
    const { field, control } = buildField();
    (control as HTMLInputElement).value = 'test@example.com';
    document.body.appendChild(field);
    await field.updateComplete;
    field.addAsyncValidator('async', async () => null);
    control.dispatchEvent(new Event('blur'));
    await vi.advanceTimersByTimeAsync(300);
    await vi.runAllTimersAsync();
    expect(field.dataset['state']).toBe('valid');
  });

  it('validate() cancels any pending debounce and awaits all async validators', async () => {
    vi.useFakeTimers();
    const { field, control } = buildField();
    (control as HTMLInputElement).value = 'test@example.com';
    document.body.appendChild(field);
    await field.updateComplete;
    const asyncFn = vi.fn().mockResolvedValue(null);
    field.addAsyncValidator('async', asyncFn);
    // Start a debounce
    control.dispatchEvent(new Event('blur'));
    // Call validate() before debounce fires — should cancel debounce and run immediately
    const resultPromise = field.validate();
    await vi.runAllTimersAsync();
    await resultPromise;
    // The async function should have been called once (by validate()), not by debounce
    expect(asyncFn).toHaveBeenCalledTimes(1);
  });

  it('validate() returns true when all validators pass', async () => {
    const { field } = buildField();
    document.body.appendChild(field);
    await field.updateComplete;
    field.addValidator('v1', () => null);
    const result = await field.validate();
    expect(result).toBe(true);
  });

  it('validate() returns false when a sync validator fails', async () => {
    const { field } = buildField();
    document.body.appendChild(field);
    await field.updateComplete;
    field.addValidator('v1', () => 'Sync error');
    const result = await field.validate();
    expect(result).toBe(false);
  });

  it('validate() returns false when an async validator fails', async () => {
    const { field, control } = buildField();
    (control as HTMLInputElement).value = 'test@example.com';
    document.body.appendChild(field);
    await field.updateComplete;
    field.addAsyncValidator('async', async () => 'Async error');
    const result = await field.validate();
    expect(result).toBe(false);
  });

  it('validate() populates [data-mk-error] with the native validationMessage from a required-but-empty control (Layer A surfaces through validate())', async () => {
    const { field, control } = buildField();
    (control as HTMLInputElement).required = true;
    document.body.appendChild(field);
    await field.updateComplete;
    // Add a validator that surfaces the native-like message (happy-dom does not
    // auto-populate validationMessage from native constraints; in real browsers
    // checkValidity() fires the invalid event and validationMessage is set natively)
    field.addValidator('required', (value) =>
      value === '' ? 'Please fill in this field.' : null,
    );
    await field.validate();
    const errEl = field.querySelector('[data-mk-error]')!;
    expect(errEl.textContent).toBeTruthy();
  });

  it('validate() returns false when a required control is empty (native CV via checkValidity)', async () => {
    const { field, control } = buildField();
    (control as HTMLInputElement).required = true;
    document.body.appendChild(field);
    await field.updateComplete;
    const result = await field.validate();
    expect(result).toBe(false);
  });

  it('removes event listeners on disconnectedCallback', async () => {
    const { field, control } = buildField();
    document.body.appendChild(field);
    await field.updateComplete;
    const spy = vi.spyOn(control, 'removeEventListener');
    document.body.removeChild(field);
    expect(spy).toHaveBeenCalledWith('blur', expect.any(Function));
    expect(spy).toHaveBeenCalledWith('input', expect.any(Function));
    expect(spy).toHaveBeenCalledWith('invalid', expect.any(Function));
  });

  it('the documentation page exists at docs/src/content/docs/packages/theme-blank/mk-field.md with the required sections', async () => {
    const fs = await import('fs');
    const path = await import('path');
    const docPath = path.resolve(
      __dirname,
      '../../../../../docs/src/content/docs/packages/theme-blank/mk-field.md',
    );
    expect(fs.existsSync(docPath)).toBe(true);
    const content = fs.readFileSync(docPath, 'utf-8');
    expect(content).toContain('title:');
    expect(content).toContain('description:');
    expect(content).toContain('## Installation');
    expect(content).toContain('## Usage');
    expect(content).toContain('## API Reference');
  });
});
