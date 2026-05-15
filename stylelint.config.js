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
  },
};

export default config;
