import { MkElement, registerBase, requireInnerControl } from '@markommerce/frontend';
import '../../css/components/mk-fieldset.css';

export class MkFieldsetElement extends MkElement {
  override connectedCallback(): void {
    super.connectedCallback();
    requireInnerControl(this, 'fieldset');
  }
}

registerBase('mk-fieldset', MkFieldsetElement);
