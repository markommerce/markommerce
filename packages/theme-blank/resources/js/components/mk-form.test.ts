// @vitest-environment happy-dom
import { afterEach, beforeAll, describe, expect, it, vi } from 'vitest';
import { defineAllComponents, getRegisteredComponents } from '@markommerce/frontend';
import './mk-field';
import './mk-input';
import './mk-form';
import { MkFormElement } from './mk-form';
import { MkElement } from '@markommerce/frontend';
import type { MkFieldElement } from './mk-field';

beforeAll(() => {
  if (!customElements.get('mk-form')) {
    defineAllComponents();
  }
});

afterEach(() => {
  document.body.innerHTML = '';
  vi.restoreAllMocks();
});

function buildForm(): {
  form: MkFormElement;
  innerForm: HTMLFormElement;
  fields: MkFieldElement[];
} {
  const form = document.createElement('mk-form') as MkFormElement;
  const innerForm = document.createElement('form');
  innerForm.method = 'post';
  innerForm.action = '/checkout';

  const field1 = document.createElement('mk-field') as MkFieldElement;
  const label1 = document.createElement('label');
  label1.textContent = 'Name';
  const input1 = document.createElement('input');
  input1.type = 'text';
  input1.name = 'name';
  field1.appendChild(label1);
  field1.appendChild(input1);

  const field2 = document.createElement('mk-field') as MkFieldElement;
  const label2 = document.createElement('label');
  label2.textContent = 'Email';
  const input2 = document.createElement('input');
  input2.type = 'email';
  input2.name = 'email';
  field2.appendChild(label2);
  field2.appendChild(input2);

  innerForm.appendChild(field1);
  innerForm.appendChild(field2);
  form.appendChild(innerForm);

  return { form, innerForm, fields: [field1, field2] };
}

describe('mk-form', () => {
  it('registers under the tag name "mk-form"', () => {
    const registered = getRegisteredComponents().find((c) => c.tagName === 'mk-form');
    expect(registered).toBeDefined();
    expect(registered!.base).toBe(MkFormElement);
  });

  it('MkFormElement extends MkElement', () => {
    expect(Object.getPrototypeOf(MkFormElement)).toBe(MkElement);
  });

  it('sets novalidate on the inner form element in connectedCallback', async () => {
    const { form, innerForm } = buildForm();
    document.body.appendChild(form);
    await form.updateComplete;
    expect(innerForm.getAttribute('novalidate')).toBe('');
  });

  it('does not remove its light-DOM form child after upgrade', async () => {
    const { form, innerForm } = buildForm();
    document.body.appendChild(form);
    await form.updateComplete;
    expect(form.querySelector('form')).toBe(innerForm);
  });

  it('emits mk-submit with FormData detail when all mk-field descendants are valid on form submit', async () => {
    const { form, innerForm, fields } = buildForm();
    document.body.appendChild(form);
    await form.updateComplete;
    // wait for fields to connect
    for (const f of fields) await f.updateComplete;

    const events: CustomEvent[] = [];
    form.addEventListener('mk-submit', (e) => events.push(e as CustomEvent));

    innerForm.dispatchEvent(new SubmitEvent('submit', { bubbles: true, cancelable: true }));
    await new Promise((r) => setTimeout(r, 0));

    expect(events).toHaveLength(1);
    expect(events[0]!.detail.formData).toBeInstanceOf(FormData);
  });

  it('prevents native form submission (event.preventDefault) regardless of validation outcome', async () => {
    const { form, innerForm, fields } = buildForm();
    document.body.appendChild(form);
    await form.updateComplete;
    for (const f of fields) await f.updateComplete;

    const submitEvent = new SubmitEvent('submit', { bubbles: true, cancelable: true });
    innerForm.dispatchEvent(submitEvent);
    await new Promise((r) => setTimeout(r, 0));

    expect(submitEvent.defaultPrevented).toBe(true);
  });

  it('emits mk-invalid with an array of invalid mk-field elements when any field fails validation', async () => {
    const { form, innerForm, fields } = buildForm();
    // Make the first field invalid
    const input = fields[0]!.querySelector('input')!;
    input.required = true;
    document.body.appendChild(form);
    await form.updateComplete;
    for (const f of fields) await f.updateComplete;

    const events: CustomEvent[] = [];
    form.addEventListener('mk-invalid', (e) => events.push(e as CustomEvent));

    innerForm.dispatchEvent(new SubmitEvent('submit', { bubbles: true, cancelable: true }));
    await new Promise((r) => setTimeout(r, 0));

    expect(events).toHaveLength(1);
    expect(Array.isArray(events[0]!.detail.fields)).toBe(true);
    expect(events[0]!.detail.fields).toContain(fields[0]);
  });

  it('focuses the first invalid field\'s inner control when validation fails', async () => {
    const { form, innerForm, fields } = buildForm();
    const input = fields[0]!.querySelector('input')! as HTMLInputElement;
    input.required = true;
    document.body.appendChild(form);
    await form.updateComplete;
    for (const f of fields) await f.updateComplete;

    const focusSpy = vi.spyOn(input, 'focus');

    innerForm.dispatchEvent(new SubmitEvent('submit', { bubbles: true, cancelable: true }));
    await new Promise((r) => setTimeout(r, 0));

    expect(focusSpy).toHaveBeenCalled();
  });

  it('awaits all mk-field.validate() calls in parallel before dispatching any event', async () => {
    const { form, innerForm, fields } = buildForm();
    document.body.appendChild(form);
    await form.updateComplete;
    for (const f of fields) await f.updateComplete;

    const resolvers: Array<(v: boolean) => void> = [];
    const callOrder: number[] = [];

    fields.forEach((f, i) => {
      f.validate = vi.fn(
        () =>
          new Promise<boolean>((resolve) => {
            callOrder.push(i);
            resolvers.push(resolve);
          }),
      );
    });

    const events: CustomEvent[] = [];
    form.addEventListener('mk-submit', (e) => events.push(e as CustomEvent));
    form.addEventListener('mk-invalid', (e) => events.push(e as CustomEvent));

    innerForm.dispatchEvent(new SubmitEvent('submit', { bubbles: true, cancelable: true }));
    // Neither event should fire yet — promises are still pending
    await new Promise((r) => setTimeout(r, 0));
    expect(events).toHaveLength(0);
    expect(callOrder).toEqual([0, 1]);

    // Both validate() calls were started before either resolved
    resolvers.forEach((res) => res(true));
    await new Promise((r) => setTimeout(r, 0));
    expect(events).toHaveLength(1);
    expect(events[0]!.type).toBe('mk-submit');
  });

  it('removes the submit event listener from the inner form on disconnectedCallback', async () => {
    const { form, innerForm } = buildForm();
    document.body.appendChild(form);
    await form.updateComplete;

    const spy = vi.spyOn(innerForm, 'removeEventListener');
    document.body.removeChild(form);

    expect(spy).toHaveBeenCalledWith('submit', expect.any(Function));
  });

  it('ignores concurrent submit events while a previous submit is still pending (reentrancy guard)', async () => {
    const { form, innerForm, fields } = buildForm();
    document.body.appendChild(form);
    await form.updateComplete;
    for (const f of fields) await f.updateComplete;

    let resolveFirst!: (v: boolean) => void;
    let callCount = 0;

    fields.forEach((f) => {
      f.validate = vi.fn(
        () =>
          new Promise<boolean>((resolve) => {
            callCount++;
            if (callCount <= fields.length) {
              resolveFirst = resolve;
            }
            // subsequent calls should not happen
          }),
      );
    });

    const events: CustomEvent[] = [];
    form.addEventListener('mk-submit', (e) => events.push(e as CustomEvent));

    // First submit
    innerForm.dispatchEvent(new SubmitEvent('submit', { bubbles: true, cancelable: true }));
    await new Promise((r) => setTimeout(r, 0));

    // Second submit while first is pending
    innerForm.dispatchEvent(new SubmitEvent('submit', { bubbles: true, cancelable: true }));
    await new Promise((r) => setTimeout(r, 0));

    // Validate calls should be exactly fields.length (only first submit)
    expect(callCount).toBe(fields.length);

    resolveFirst(true);
    // Need to resolve all field validators
    await new Promise((r) => setTimeout(r, 0));
    await new Promise((r) => setTimeout(r, 0));
  });

  it('the documentation page exists at docs/src/content/docs/packages/theme-blank/mk-form.md with the required sections', async () => {
    const fs = await import('fs');
    const path = await import('path');
    const docPath = path.resolve(
      __dirname,
      '../../../../../docs/src/content/docs/packages/theme-blank/mk-form.md',
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
