import tseslint from 'typescript-eslint';
import litPlugin from 'eslint-plugin-lit';

export default tseslint.config(
  ...tseslint.configs.recommended,
  {
    plugins: {
      lit: litPlugin,
    },
    rules: {
      ...litPlugin.configs.recommended.rules,
      '@typescript-eslint/no-explicit-any': 'error',
      'no-unused-vars': 'off',
      '@typescript-eslint/no-unused-vars': ['error', { argsIgnorePattern: '^_' }],
    },
  },
  {
    ignores: ['node_modules/**', 'public/build/**', 'vendor/**'],
  },
);
