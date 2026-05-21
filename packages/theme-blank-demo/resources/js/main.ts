import '@markommerce/theme-blank';
// Hand-maintained module-load list.
import './extensions';
// Showcase demo interactions (event delegation for toast/modal/drawer buttons).
import './showcase';
// Finally, compose mixins onto bases and define the elements.
import { defineAllComponents } from '@markommerce/frontend';
defineAllComponents();
