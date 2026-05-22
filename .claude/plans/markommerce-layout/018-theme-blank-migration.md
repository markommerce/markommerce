# Task 018: markommerce/theme-blank Migration

**Status**: completed
**Depends on**: 004, 009
**Retry count**: 0

## Description
Migrate `markommerce/theme-blank`'s layout classes from `marko/layout`'s `#[Component]`-based classes to the new `LayoutDefinition` interface, so they can serve as `extends:` targets for commerce layouts. Drop the `marko/layout` dependency.

## Context
- IMPORTANT — actual class names: `markommerce/theme-blank` ships **five** layout classes in `Markommerce\ThemeBlank\Layout\`, NOT the `OneColumnLayout`/`TwoColumnLayout` pair the plan loosely referenced. The real classes (verified against `packages/theme-blank/src/Layout/`) are:
  - `OneColumnLayout` → template `theme-blank::layout/1column`, slots `['content']`
  - `TwoColumnsLeftLayout` → template `theme-blank::layout/2columns-left`, slots `['content', 'sidebar-left']`
  - `TwoColumnsRightLayout` → template `theme-blank::layout/2columns-right`, slots `['content', 'sidebar-right']`
  - `ThreeColumnsLayout` → template `theme-blank::layout/3columns`, slots `['content', 'sidebar-left', 'sidebar-right']`
  - `EmptyLayout` → template `theme-blank::layout/empty`, slots `['content']`
  ALL FIVE must be migrated — leaving any on `marko/layout` blocks dropping the dependency.
- Each currently uses `marko/layout`'s `#[Component(template:..., slots:[...])]` attribute.
- New model: each becomes a class implementing `LayoutDefinition` (task 004) with `public static function define(): Layout`. The returned `Layout` declares the column slot vocabulary using each class's exact slot list above, carries the matching `template`, and has no controller `handle` (it is a base layout only ever used via `extends:`).
- A base layout used purely as an `extends:` target has no route handle. Task 004's `Layout` value object must allow a null/absent handle, and task 009's resolution must tolerate a handle-less `Layout` when it is reached via `extends:` (and never treat it as a routable layout). This task DEPENDS ON task 009 so the resolution contract is already settled — do not start this task until 009's `handleKey` and handle-less handling are defined. Document the representation used.
- NOTE: the `LayoutDefinition` form does not carry the `template` on a class attribute anymore. Where the column template name lived on the old `#[Component(template:...)]` attribute, decide how the new `Layout` value object carries it (e.g. a `template` field on `Layout`, or a root `Place` wrapping the template). Coordinate with task 004's `Layout` shape; if task 004 has no template field, flag it — the renderer (task 013) needs to know which Latte file to render for a base layout.
- The theme templates (Latte files under `packages/theme-blank/resources/views/layout/`) stay as-is — only the layout *classes* change. Verify the slot names in `define()` match the `{slot ...}` placeholders in the templates (`1column.latte` declares `{slot content}{/slot}`; check the others).
- Remove `marko/layout` from `packages/theme-blank/composer.json` `require`; add `markommerce/layout`.
- Update `packages/theme-blank/tests/Unit/Layout/LayoutClassesTest.php` — it currently reflects on the `marko/layout` `#[Component]` attribute and asserts the old slot lists; rewrite it for the `LayoutDefinition` shape.
- Patterns to follow: existing layout classes in `packages/theme-blank/src/Layout/`; `_plan.md` `LayoutDefinition` description.

## Requirements (Test Descriptions)
- [ ] `it defines OneColumnLayout as a LayoutDefinition`
- [ ] `it defines TwoColumnsLeftLayout as a LayoutDefinition`
- [ ] `it defines TwoColumnsRightLayout as a LayoutDefinition`
- [ ] `it defines ThreeColumnsLayout as a LayoutDefinition`
- [ ] `it defines EmptyLayout as a LayoutDefinition`
- [ ] `it declares a content slot on the one-column layout`
- [ ] `it declares content and sidebar-left slots on the two-columns-left layout`
- [ ] `it declares content, sidebar-left and sidebar-right slots on the three-columns layout`
- [ ] `it returns a Layout with no route handle from a base layout define method`
- [ ] `it serves as an extends target for another layout`
- [ ] `it no longer depends on marko/layout in composer.json`

## Acceptance Criteria
- All requirements have passing tests
- All five layouts implement `LayoutDefinition`
- `packages/theme-blank/composer.json` no longer requires `marko/layout`
- Slot names in `define()` match the existing template placeholders for every layout
- `LayoutClassesTest.php` rewritten for the new shape and passing
- Handle-less base-layout representation documented in Implementation Notes

## Implementation Notes
(Left blank - filled in by programmer during implementation)
