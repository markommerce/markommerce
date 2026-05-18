/** @type {import('stylelint').Config} */
const config = {
  extends: ['stylelint-config-standard'],
  rules: {
    // Allow @layer at-rule (CSS cascade layers)
    'at-rule-no-unknown': [
      true,
      {
        ignoreAtRules: ['layer'],
      },
    ],
    // Allow CSS custom properties (--*) without warnings
    'custom-property-no-missing-var-function': null,
    'property-no-unknown': [
      true,
      {
        ignoreProperties: [/^--/],
      },
    ],
    // Allow BEM naming convention (block__element--modifier)
    'selector-class-pattern': [
      '^[a-z][a-z0-9]*(-[a-z0-9]+)*(__[a-z][a-z0-9]*(-[a-z0-9]+)*)?(--[a-z][a-z0-9]*(-[a-z0-9]+)*)?$',
      {
        message: 'Expected class selector to be BEM or kebab-case',
      },
    ],
  },
};

export default config;
