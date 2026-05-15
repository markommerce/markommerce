import { html } from 'lit';
import { property } from 'lit/decorators.js';
import type { TemplateResult } from 'lit';
import type { Constructor } from '@markommerce/frontend';
import type { MarkommerceCounterElement } from '../components/MarkommerceCounter';

type CounterBase = Constructor<MarkommerceCounterElement>;

export function LabelSuffixMixin<TBase extends CounterBase>(Base: TBase): TBase {
  class WithSuffix extends Base {
    @property({ type: String }) suffix = '';

    override renderLabel(): TemplateResult {
      return html`<span>${this.count}${this.suffix}</span>`;
    }
  }
  return WithSuffix as unknown as TBase;
}
