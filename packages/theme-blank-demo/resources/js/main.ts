// Cascade-layer baseline first — must be the very first stylesheet so layer order is established.
import '@markommerce/frontend/css/layers.css';
// Open Props raw tokens — unlayered, sits in the global scope.
import 'open-props/style.css';
// Markommerce semantic tokens (inside @layer tokens, references Open Props).
import '@markommerce/theme-blank/css/tokens.css';
// Base styles reset (inside @layer base, references --mk-* tokens).
import '@markommerce/theme-blank/css/base.css';
// Page layout structures (inside @layer theme, defines grid column structures).
import '@markommerce/theme-blank/css/layouts.css';
// Theme-blank components
import '@markommerce/theme-blank';
// Hand-maintained module-load list.
import './extensions';
// Showcase demo interactions (event delegation for toast/modal/drawer buttons).
import './showcase';
// Finally, compose mixins onto bases and define the elements.
import { defineAllComponents } from '@markommerce/frontend';
defineAllComponents();
