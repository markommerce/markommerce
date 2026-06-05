# Task 005: mk-infinite-scroll — backward button + scroll-spy + canonical fix (keep forward auto)

**Status**: complete
**Depends on**: 003
**Retry count**: 0

## Description
Rework `mk-infinite-scroll` to use the shared loader: keep the existing bottom-sentinel forward auto-load, ADD a clickable "Load previous" button (no top sentinel), register batches with the scroll-spy, and fix the address bar to use the canonical page URL via `replaceState`.

## Context
- File: `packages/theme-blank/resources/js/components/mk-infinite-scroll.ts`. Import `./pagination-loader` (Task 003).
- vitest on HOST. Mock `fetch`, `IntersectionObserver`, `history.replaceState`, scroll metrics (stub `scrollHeight`/`scrollTop`/`getBoundingClientRect` per Task 003 — happy-dom has no layout).
- **Existing tests to keep green:** `mk-infinite-scroll.test.ts` has `it triggers a load when the sentinel intersects` (uses the `MockIntersectionObserver` that captures the callback) and `it exposes an accessible load-more fallback button`. Both must still pass after the refactor — the sentinel/IO forward path and the injected fallback button stay. The current source calls `history.pushState(..., fragmentUrl)` inside `#loadNext`; that call is REMOVED (no test currently asserts it, so no rewrite needed there — just delete it). Do not break the IO mock contract: the component must still construct an `IntersectionObserver`, `observe` the sentinel, and re-observe after a forward load.
- **Keep**: the bottom sentinel + `IntersectionObserver` that auto-loads the NEXT page forward (append). Refactor its fetch/parse/append to call the shared `loadFragment` + `appendCards`.
- **Add (button-only, NO top sentinel — per decision):** a "Load previous" button. Per Task 002's pinned structure it is rendered as a SIBLING **before** `<mk-grid>` (NOT a child of this element) — locate it via `grid.parentElement?.querySelector('[data-role="load-previous"]')`. The component reads `data-prev`/`data-canonical` from ITSELF. Click → `loadFragment(data-prev)` → `prependCardsAnchored` → chain `data-prev` → remove the button when page 1 reached. (The forward fallback "Load more" button it injects stays a child of the element, below the grid.)
- Keep the existing forward "Load more" accessible fallback button it already injects (`#ensureFallbackButton`), now routed through the shared loader.
- **URL FIX**: replace the current `history.pushState(…, fragmentUrl)` with a `ScrollSpy` (`onChange = replaceState(canonical)`). Register the initial batch from `data-canonical` + grid's first card. Register each appended/prepended batch. NEVER pushState, NEVER the fragment endpoint URL.
- Do NOT add a top sentinel — previous is button-triggered only (avoids auto backward-load on entry).

## Requirements (Test Descriptions)
- [x] `it auto-loads and appends the next page when the bottom sentinel intersects`
- [x] `it prepends the previous page anchored when the load-previous button is clicked`
- [x] `it does not auto-load a previous page on initial connect`
- [x] `it removes the load-previous button when page one is reached`
- [x] `it replaceStates the canonical full-page url (never the fragment endpoint url)`
- [x] `it updates the url to the topmost batch canonical on scroll`

## Acceptance Criteria
- Forward auto-load preserved; backward is button-only; URL uses canonical `replaceState` + scroll-spy; no auto backward-load on entry.
- vitest tests pass.
- All requirements have passing tests.

## Implementation Notes
