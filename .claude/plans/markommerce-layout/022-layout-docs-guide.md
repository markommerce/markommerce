# Task 022: Create docs guide for `markommerce/layout`

**Status**: completed
**Depends on**: 021
**Retry count**: 0

## Description
Write a comprehensive developer guide for the `markommerce/layout` system at `docs/src/content/docs/guides/working-with-layouts.md`. This guide teaches third-party developers how to define layouts, create component DTOs, wire context providers, use repeat slots, and extend layouts from another module. It references `markommerce/layout-demo` as the worked example throughout.

## Context
- The `docs/src/content/docs/packages/layout.md` page already exists as an API reference (all classes, methods, operations, source factory). The new guide is complementary — it is a **how-to guide**, not a reference. It explains *why* and *when* to use each concept, with worked examples.
- Guide format: follows the same structure and style as `docs/src/content/docs/guides/writing-a-frontend-module.md` — prose introduction, H2 sections for each major concept, fenced code blocks with `title="..."`, and a "Next Steps" section at the end linking to the API reference.
- All code examples must be valid against the final API (the layout, place, source, slot, extension operation constructors as built in tasks 001-019). Do not invent parameters that don't exist.
- The guide should cover (in order):
  1. **Overview** — What the layout system is, why it exists (placement-agnostic components, typed compile-validated trees, extension vocabulary). One paragraph.
  2. **Defining a layout** — Create `{module}/layout/{name}.php` returning a `Layout`. Show `handle`, `extends`, `context`, `slots`. Use `layout-demo`'s gallery example.
  3. **Component data DTOs** — Write a `data()` method returning a typed DTO. Show a `readonly` DTO, mention `ExtensibleData` for plugin-extensible DTOs.
  4. **Context providers** — Implement `ContextProvider`, wire with `Provide`, read via `Source::context()`.
  5. **Sources** — Explain all six: `Source::route()`, `Source::query()`, `Source::context()`, `Source::iterated()`, `Source::parentData()`, `Source::service()` with concise examples for each. All six must appear in the guide.
  6. **Repeat slots** — `Slot::repeat()` for iterating a DTO collection. Show `dataKey`, `yields`, `as`, `children`.
  7. **Extending a layout** — Create `{module}/layout/extensions/{name}.php` returning a `LayoutExtension`. Walk through `InsertBefore`, `WrapWith`, `MergeProps`, `Remove`. Show priority ordering.
  8. **Compiling** — `vendor/bin/marko layout:compile`. Mention `CompileIfStaleMiddleware` for dev mode. Mention artifact path.
  9. **Next Steps** — links to `markommerce/layout` API reference, `markommerce/layout-demo` package, `markommerce/theme-blank` layout definitions.
- Also update `docs/src/content/docs/packages/layout.md`:
  - Add a `## Guides` section near the top (after the description paragraph, before `## Installation`) linking to the new guide.
  - Add a dedicated `### ContextProvider` subsection under `## Contracts` (or as a standalone `## Context Providers` section in the Usage area) explaining the interface signature (`provide(array $props): object`), how `$props` keys map to the `Provide` value object's `props` array, and that the return value is stored in the context bag keyed by the `Provide::$token` class. This is currently only a one-liner in the contracts table and is not sufficient for a developer implementing their first provider.
- The `doc-updater` agent does not need to run here — the guide file is new, not auto-generated from package changes.

## Requirements (Test Descriptions)
- [x] `it has a guide file at guides/working-with-layouts.md`
- [x] `it has a title frontmatter field`
- [x] `it has a description frontmatter field`
- [x] `it documents defining a layout`
- [x] `it documents context providers`
- [x] `it documents repeat slots`
- [x] `it documents layout extensions`
- [x] `it documents the layout:compile command`
- [x] `it links to the layout API reference`

## Acceptance Criteria
- `docs/src/content/docs/guides/working-with-layouts.md` exists with valid frontmatter (`title`, `description`)
- File is valid Markdown (no broken fences, balanced frontmatter)
- All code examples reference real classes/methods from the built API
- `docs/src/content/docs/packages/layout.md` has a "## Guides" section linking to the new guide
- Tests in `packages/layout/tests/` (or a dedicated docs-test) assert the guide file exists and contains required headings
- `composer test` green

## Implementation Notes
- Write tests as a small `tests/Unit/DocsGuideTest.php` in `packages/layout/` that reads the guide markdown file. Use `dirname(__DIR__, 4) . '/docs/src/content/docs/guides/working-with-layouts.md'` to locate the file (from `packages/layout/tests/Unit/` to repo root is 4 levels up). Mirror the pattern of `ReadmeTest.php` for assertions on headings/phrases.
- Code blocks in the guide: use real class names from the `markommerce/layout` and `markommerce/layout-demo` packages. Don't invent fictional namespaces.
- Verify constructor signatures against the actual classes before writing examples:
  - `new Layout(handle: ..., extends: ..., context: [], slots: [...], template: ?string)` — `extends` is a `class-string` or `null`; `slots` values are `list<Place>` or `Slot` (from `Slot::repeat()`).
  - `new Place(component: ..., name: ?string, props: [...], slots: [...], template: '')` — `name` MUST match `^[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)+$` if non-null.
  - `Slot::repeat(dataKey: ..., yields: ..., as: ..., children: [...])` — static factory.
  - `new Provide(token: ..., provider: ..., props: [...])` — `props` keys map to `provide(array $props)`'s array keys.
  - `new LayoutExtension(handle: ..., operations: [...], priority: 0)`.
  - `Source::route($name, $as = 'string')`, `Source::query($name, $default = null, $as = 'string')`, `Source::context($token, $path = null)`, `Source::iterated($token, $path = null)`, `Source::parentData($key, $as = 'string')`, `Source::service($class)`. Allowed `$as` values for `route`/`query`/`parentData`: `'int'`, `'string'`, `'bool'`.
- Keep each section concise — this is a how-to, not an exhaustive reference. Cross-link to `packages/layout.md` for full API tables.
- Use `Markommerce\LayoutDemo\` as the namespace for all demo code examples, mirroring the `layout-demo` package built in task 021. Concrete class names referenced in the guide (e.g. `GalleryComponent`, `ItemComponent`, `GalleryToken`, `GalleryContextProvider`, `ItemIteration`) must match the classes that task 021 actually creates.
- Update `docs/src/content/docs/packages/layout.md`:
  - Insert a `## Guides` section between the opening description paragraphs and `## Installation`, with one bullet linking to the new guide via `/docs/guides/working-with-layouts/`.
  - Add a `### Context providers` subsection inside `## Usage` (between the existing "Implementing a layout definition" and "Sources" subsections). Show the full interface signature, explain that `$props` keys come from `Provide::$props`, and that the returned object is keyed in the context bag by `Provide::$token`. Include a short code example (a concrete implementation returning a domain entity).
