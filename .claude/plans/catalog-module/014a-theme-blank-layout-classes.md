# Task 014a: theme-blank Layout PHP classes + `{block}`→`{slot}` conversion

**Status**: complete
**Depends on**: 011
**Retry count**: 0

## Description
Add a Layout PHP class for every leaf layout that theme-blank currently ships (5 classes total), and convert each leaf layout's inner placeholder syntax from latte's native `{block X}{/block}` to `marko/view-latte`'s `{slot X}{/slot}` so that `marko/layout`'s `LayoutProcessor` can actually inject content into them. After this task, any consumer can write `#[Layout(Markommerce\ThemeBlank\Layout\<Whichever>Layout::class)]` and have their `#[Component]` slot into the matching named slot.

This task is PHP + latte only. The CSS-bootstrap relocation (theme-blank index.ts changes, theme-blank-demo main.ts trim) and the catalog frontend scaffold live in task 014b.

## Context

### Why the Layout classes belong in theme-blank
- `marko/layout`'s `#[Layout(SomeClass::class)]` requires a PHP class — strictly a class-string, no template-path or config-array alternative (verified against `marko/packages/layout/src/Attributes/Layout.php:12`). The class is a thin marker carrying `#[Component(template: '...', slots: [...])]` that points at the layout latte template and declares the slot names.
- `packages/theme-blank/src/` is currently empty (only `.gitkeep`); theme-blank ships zero PHP. That is the gap.
- The layout latte templates belong to theme-blank, so the wrapper classes belong to theme-blank too. Putting them in a consumer package (e.g. catalog) leaves the next consumer with the same problem.
- One class is added for **every leaf layout currently shipped**: `1column`, `2columns-left`, `2columns-right`, `3columns`, `empty`.
- `base.latte` is **not** wrapped. It's a parent template — consumers should never `#[Layout]` it directly; they should pick a leaf. Skipping `BaseLayout` also keeps `base.latte` free to use `{block}` inheritance placeholders so the leaf layouts can continue to extend it via `{block X}` overrides.

### Slot-syntax conversion (critical)
- **Mechanism mismatch**: theme-blank's current layout templates use latte's native `{block X}{/block}` placeholders. `marko/view-latte`'s `SlotExtension` (`marko/packages/view-latte/src/.../SlotExtension.php`) only recognises `{slot X}{/slot}` — that's the directive it compiles into `echo $slots[$name] ?? ''`. `{block}` is gone (compiled into template-inheritance control flow) by the time `LayoutProcessor` injects slot data via `['slots' => $slots]`. So the existing `{block content}{/block}` placeholders do NOT receive slot data from marko/layout. Confirmed by reading `LayoutProcessor.php` and `SlotExtension`.
- **Conversion rule**: in each leaf layout (`1column.latte`, `2columns-left.latte`, `2columns-right.latte`, `3columns.latte`, `empty.latte`), every `{block X}{/block}` *placeholder* (the inner slot — not the outer block that overrides a base block) becomes `{slot X}{/slot}`. The outer blocks that override `base.latte`'s structure (`{block main}`, `{block body}`) stay as `{block}` — they're inheritance, not slot injection.
- Concrete diffs per file:

  **1column.latte**
  ```latte
  {layout 'theme-blank::layout/base'}
  {block main}
  <mk-container>
      {slot content}{/slot}      {* was: {block content}{/block} *}
  </mk-container>
  {/block}
  ```

  **2columns-left.latte**
  ```latte
  {layout 'theme-blank::layout/base'}
  {block main}
  <mk-container>
      <mk-sidebar>
          <aside>{slot sidebar-left}{/slot}</aside>   {* was: {block sidebar-left}{/block} *}
          {slot content}{/slot}                       {* was: {block content}{/block} *}
      </mk-sidebar>
  </mk-container>
  {/block}
  ```

  **2columns-right.latte**
  ```latte
  {layout 'theme-blank::layout/base'}
  {block main}
  <mk-container>
      <mk-sidebar side="right">
          {slot content}{/slot}                       {* was: {block content}{/block} *}
          <aside>{slot sidebar-right}{/slot}</aside>  {* was: {block sidebar-right}{/block} *}
      </mk-sidebar>
  </mk-container>
  {/block}
  ```

  **3columns.latte**
  ```latte
  {layout 'theme-blank::layout/base'}
  {block main}
  <mk-container>
      <div class="mk-layout-3col">
          <aside>{slot sidebar-left}{/slot}</aside>
          {slot content}{/slot}
          <aside>{slot sidebar-right}{/slot}</aside>
      </div>
  </mk-container>
  {/block}
  ```

  **empty.latte**
  ```latte
  {layout 'theme-blank::layout/base'}
  {block body}
  {slot content}{/slot}          {* was: {block content}{/block} *}
  {/block}
  ```

- `base.latte` is **unchanged**. Its `{block title}`, `{block head-extra}`, `{block body}`, `{block header}`, `{block main}`, `{block footer}` stay as `{block}` because the leaf layouts above still override them via inheritance. Note: this means `base.latte`'s placeholders are NOT marko/layout slots — they only receive content from latte template inheritance, never from `['slots' => …]` data.
- **`{slot}` semantics when no slot data is passed**: `SlotExtension` compiles to `echo $slots[$name] ?? ''`, so missing slots render empty — safe for direct-render tests of these templates that don't pass any `$slots` array.

### Files to add/change

1. **Add Layout PHP classes** under `packages/theme-blank/src/Layout/`:
   - `OneColumnLayout.php`
     ```php
     <?php
     declare(strict_types=1);
     namespace Markommerce\ThemeBlank\Layout;
     use Marko\Layout\Attributes\Component;
     #[Component(template: 'theme-blank::layout/1column', slots: ['content'])]
     class OneColumnLayout {}
     ```
   - `TwoColumnsLeftLayout.php` — `#[Component(template: 'theme-blank::layout/2columns-left', slots: ['content', 'sidebar-left'])]`
   - `TwoColumnsRightLayout.php` — `#[Component(template: 'theme-blank::layout/2columns-right', slots: ['content', 'sidebar-right'])]`
   - `ThreeColumnsLayout.php` — `#[Component(template: 'theme-blank::layout/3columns', slots: ['content', 'sidebar-left', 'sidebar-right'])]`
   - `EmptyLayout.php` — `#[Component(template: 'theme-blank::layout/empty', slots: ['content'])]`
   - All five are non-final marker classes, no constructors, no methods.
   - Slot names match the `{slot X}{/slot}` directives in the corresponding latte file after the conversion above.

2. **Convert the slot placeholders** in each leaf layout latte (see "Slot-syntax conversion" section above). Five `.latte` files touched: `1column`, `2columns-left`, `2columns-right`, `3columns`, `empty`. `base.latte` unchanged.

### Tests to add / update
- `packages/theme-blank/tests/Feature/LayoutTemplatesTest.php` already renders the layout latte templates. Its assertions need re-checking against the `{block}` → `{slot}` conversion — anything that asserts the literal `{block content}` text in the rendered output (unlikely) must change; anything that just asserts the structural HTML wrappers (`<mk-container>`, `<mk-sidebar>`, the document chrome from `base.latte`) keeps working because that structure is identical.
- Add a parallel feature test (or extend `LayoutTemplatesTest`) that renders each leaf layout via `LatteView::renderToString($template, ['slots' => ['content' => '<span>hello</span>', ...]])` and asserts the slot content appears inside the right structural wrapper. This catches the `{slot}` directive working end-to-end.
- Add a unit test that uses reflection to assert each of the five Layout classes carries the correct `#[Component]` attribute (template path + slot names).

### Project rules
- `declare(strict_types=1)` on every PHP file.
- No `final` classes.
- No traits.
- `@throws` annotations where exceptions propagate (none expected in this task's PHP code).

## Requirements (Test Descriptions)
- [x] `it declares OneColumnLayout with a Component attribute pointing at theme-blank::layout/1column and the content slot`
- [x] `it declares TwoColumnsLeftLayout with a Component attribute pointing at theme-blank::layout/2columns-left and the content and sidebar-left slots`
- [x] `it declares TwoColumnsRightLayout with a Component attribute pointing at theme-blank::layout/2columns-right and the content and sidebar-right slots`
- [x] `it declares ThreeColumnsLayout with a Component attribute pointing at theme-blank::layout/3columns and the content, sidebar-left and sidebar-right slots`
- [x] `it declares EmptyLayout with a Component attribute pointing at theme-blank::layout/empty and the content slot`
- [x] `it converts the inner {block} placeholders to {slot} directives in every leaf layout latte`
- [x] `it leaves base.latte's inheritance blocks unchanged so the leaf layouts still extend it`
- [x] `it injects content slot data into the 1column layout via marko/view-latte SlotExtension`
- [x] `it injects sidebar-left and sidebar-right slot data into the 3columns layout`

## Acceptance Criteria
- All requirements have passing tests
- All five Layout classes (`OneColumnLayout`, `TwoColumnsLeftLayout`, `TwoColumnsRightLayout`, `ThreeColumnsLayout`, `EmptyLayout`) are discoverable by `marko/layout` (verify the layout package's class-discovery mechanism does not require explicit registration; if it does, register in `packages/theme-blank/module.php`)
- Every leaf layout latte uses `{slot X}{/slot}` for its content/sidebar placeholders; `base.latte` is byte-identical to its pre-task state
- All theme-blank tests pass: `./vendor/bin/pest packages/theme-blank`
- Code follows project standards (strict types, no `final`, no traits, etc.)

## Notes for Implementer
- **Verify discovery first**: read `marko/packages/layout/src/...` (the `DiscoveringComponentCollector` and surrounding code) to confirm whether `#[Component]` classes are auto-discovered by scanning `src/Layout/` (or `src/Component/`) directories, or whether they must be listed in `module.php`. If the latter, add explicit registration entries to `packages/theme-blank/module.php` (currently returns `[]`).
- **Slot directive sanity-check**: before touching the latte files, read `marko/packages/view-latte/src/.../SlotExtension.php` once and confirm the compiled output is `echo $slots[$name] ?? ''` (or equivalent). The plan assumes missing slots render empty — verify.
- **Render-with-inheritance check**: the leaf layouts use BOTH `{layout 'theme-blank::layout/base'}` (latte inheritance) AND `{slot X}{/slot}` (marko/view-latte slot directive) inside their overridden blocks. Add at least one test that exercises this composition — render a leaf layout with `$slots` data and assert both that base.latte's chrome appears AND that the slot data appears in the right place.
- `LayoutTemplatesTest` already renders the layout latte templates. Re-run it after the `{block}` → `{slot}` conversion and update only the assertions that break — the structural HTML (mk-container, mk-sidebar wrappers, document chrome) is unchanged, so most should pass untouched.
- Do NOT touch the catalog controller, the standalone `category.latte`, theme-blank's `index.ts`, theme-blank-demo's `main.ts`, or any JS/CSS bundle wiring. Task 014b handles JS/CSS; task 016 handles the controller swap.
- **Out of scope intentionally**: a `BaseLayout` class is NOT added. `base.latte` keeps its `{block}` inheritance placeholders so the leaf layouts can extend it. Consumers wanting a layout-less rendering should use `EmptyLayout`.
