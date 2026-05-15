import { registerBase, addMixin } from '@markommerce/frontend';
import { MarkommerceCounterElement } from './components/MarkommerceCounter';
import { LabelSuffixMixin } from './mixins/LabelSuffixMixin';

registerBase('markommerce-counter', MarkommerceCounterElement);
addMixin('markommerce-counter', LabelSuffixMixin, { source: '@markommerce/frontend-demo', priority: 100 });

export { MarkommerceCounterElement, LabelSuffixMixin };
