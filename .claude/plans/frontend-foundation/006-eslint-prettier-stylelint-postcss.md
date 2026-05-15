# Task 006: Root ESLint + Prettier + Stylelint + PostCSS config

**Status**: completed
**Depends on**: 001, 005
**Retry count**: 0

## Description

Establish the JS/CSS linting and formatting baseline at the repo root: `eslint.config.js` (flat config for ESLint 9), `prettier.config.js`, `stylelint.config.js`, and `postcss.config.js`. Configure ESLint with TypeScript + Lit plugin rules, Prettier with project-consistent formatting, Stylelint with the standard config + cascade-layer allowance, and PostCSS with `postcss-import`, `postcss-nesting`, `postcss-custom-media`, `autoprefixer`, and `cssnano` (production only) — matching the spec's PostCSS expectations.

## Context

- ESLint 9 uses flat config (`eslint.config.js`); the old `.eslintrc` form is deprecated.
- Add `@typescript-eslint/parser` and `eslint-plugin-lit`.
- Stylelint must allow `@layer` and CSS Custom Properties without warnings.
- PostCSS is consumed by Vite via `vite.config.ts`'s `css.postcss` setting (task 012).
- Add `lint:css`, `lint:js`, `format`, `format:check` npm scripts to the root `package.json`.
- Related files: `eslint.config.js` (new), `prettier.config.js` (new), `stylelint.config.js` (new), `postcss.config.js` (new), root `package.json` (update scripts).

## Requirements (Test Descriptions)

- [x] `it provides a flat-config eslint.config.js that lints TypeScript with @typescript-eslint and eslint-plugin-lit`
- [x] `it bans the use of any and disables no-unused-vars in favor of typescript-eslint's variant`
- [x] `it provides prettier.config.js with project-consistent settings (single quotes, semis, 100-char width)`
- [x] `it provides stylelint.config.js that allows @layer and CSS custom properties`
- [x] `it provides a postcss.config.js with postcss-import, postcss-nesting, postcss-custom-media, autoprefixer, and conditional cssnano`
- [x] `it adds lint:js, lint:css, format, and format:check scripts to the root package.json`
- [x] `it adds the required devDependencies (eslint, @typescript-eslint/*, eslint-plugin-lit, prettier, stylelint, stylelint-config-standard, postcss, postcss-import, postcss-nesting, postcss-custom-media, autoprefixer, cssnano) to the root package.json`
- [x] `npm run lint:js exits 0 on the empty kernel scaffold`
- [x] `npm run lint:css exits 0 on the empty kernel scaffold`

## Acceptance Criteria

- All four config files parse without error.
- `npm install` resolves all new devDependencies.
- Lint scripts run inside the Node Docker service and exit 0.

## Implementation Notes

- Created `/home/michal/www/marko/markommerce/eslint.config.js` using ESLint 9 flat config format with `typescript-eslint` (the combined parser+plugin monorepo package) and `eslint-plugin-lit`. Bans `@typescript-eslint/no-explicit-any`, disables base `no-unused-vars` in favor of `@typescript-eslint/no-unused-vars`.
- Created `/home/michal/www/marko/markommerce/prettier.config.js` with `singleQuote: true`, `semi: true`, `printWidth: 100`.
- Created `/home/michal/www/marko/markommerce/stylelint.config.js` extending `stylelint-config-standard`, allowing `@layer` via `at-rule-no-unknown` with `ignoreAtRules: ['layer']`, and setting `custom-property-no-missing-var-function: null` and `property-no-unknown` to tolerate `--*` custom properties.
- Created `/home/michal/www/marko/markommerce/postcss.config.js` with all required plugins, conditionally adding `cssnano` when `NODE_ENV === 'production'`.
- Updated root `package.json` to add `lint:js`, `lint:css`, `format`, and `format:check` scripts and all required devDependencies.
- Fixed pre-existing lint errors in `packages/frontend/resources/js/events.ts` (added `eslint-disable-next-line` comments for intentional empty interfaces used for declaration merging) and `events.test.ts` (removed unused `vi` and `MarkommerceEventMap` imports).
- Ran `npm install` successfully; `npm run lint:js` and `npm run lint:css` both exit 0.
