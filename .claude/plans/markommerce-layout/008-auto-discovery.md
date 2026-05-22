# Task 008: Layout & Extension Auto-Discovery

**Status**: completed
**Depends on**: 002, 004, 007
**Retry count**: 0

## Description
Create the discovery component that scans every Marko module for layout and extension source files, `require`s each, and collects the returned `Layout` and `LayoutExtension` instances. This is the input stage of the compiler.

## Context
- Scan paths: `<module>/layout/*.php` for layouts, `<module>/layout/extensions/*.php` for extensions. (Note: `<module>` here is the package root, not `src/`.)
- Enumerate modules via `marko/core`'s `ModuleRepositoryInterface::all()` — each `ModuleManifest` has a `path`.
- Each layout file must `return` a `Layout`; each extension file must `return` a `LayoutExtension`. A file returning the wrong type (or nothing) is a loud error — name the file and what it returned. Use a `LayoutException` subclass from task 002 (the catalog has no "wrong return type" entry — add one, e.g. `InvalidLayoutFileException`, as part of this task and note it; OR reuse the closest existing one and document the choice). This is why this task now depends on 002.
- The discovery component returns a structured result: `list<Layout>` + `list<LayoutExtension>`, each tagged with its source file path (the compiler needs the path for error messages).
- IMPORTANT — `require` caching: PHP caches `require`/`require_once` by path; a file loaded twice returns the *same* value. Use `require` (not `require_once`) and accept that re-running discovery in the same process (e.g. the dev middleware recompiling) returns cached instances — that is fine because `Layout`/`LayoutExtension` are immutable value objects. Do NOT mutate the returned objects. If a test needs a fresh load it must use a distinct file path.
- Do NOT apply extensions or validate here — discovery only loads and type-checks the top-level return value.
- `LayoutDefinition` classes referenced by `extends:` are NOT discovered by file scan — they are resolved by class name during the resolution phase (task 009). Discovery only handles `layout/` directory files.
- Patterns to follow: `marko/layout/src/DiscoveringComponentCollector.php` (module iteration + file scan), `marko/core/Discovery/ClassFileParser`.
- Reuse `ClassFileParser::findPhpFiles()` if it fits; otherwise a `glob` per module directory is acceptable.

## Requirements (Test Descriptions)
- [ ] `it discovers layout files from a module layout directory`
- [ ] `it discovers extension files from a module layout extensions directory`
- [ ] `it tags each discovered layout with its source file path`
- [ ] `it tags each discovered extension with its source file path`
- [ ] `it returns an empty result for a module with no layout directory`
- [ ] `it scans across multiple modules`
- [ ] `it throws a loud error when a layout file does not return a Layout`
- [ ] `it throws a loud error when an extension file does not return a LayoutExtension`

## Acceptance Criteria
- All requirements have passing tests
- Discovery is read-only — it loads files but does not apply or validate
- Wrong-return-type errors name the file and the actual return type
- Code follows code standards

## Implementation Notes
(Left blank - filled in by programmer during implementation)
