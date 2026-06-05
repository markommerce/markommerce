# Task 004: mk-load-more — forward + backward buttons, scroll-spy, canonical replaceState

**Status**: complete
**Depends on**: 003
**Retry count**: 0

## Description
Rework `mk-load-more` to use the shared loader: own a "Load more" button (forward, append) and a "Load previous" button (backward, prepend + scroll-anchored), chain `data-next`/`data-prev`, register batches with the scroll-spy, and update the address bar to the canonical page URL via `replaceState`.

## Context
- File: `packages/theme-blank/resources/js/components/mk-load-more.ts`. Import the shared module from Task 003 (`./pagination-loader`).
- vitest on HOST. Mock `fetch`, `history.replaceState`, scroll metrics (stub `scrollHeight`/`scrollTop`/`getBoundingClientRect` per Task 003 — happy-dom has no layout).
- **BREAKING: the EXISTING `mk-load-more.test.ts` tests must be REWRITTEN, not just appended to.** Two current tests encode the OLD behavior this task removes:
  - `it updates the browser url to the next page via history api` asserts `history.pushState(null, '', '/catalog/category/1/page?page=3')`. This task removes `pushState` entirely (canonical `replaceState` via scroll-spy instead). Rewrite this test to assert `replaceState` with the CANONICAL url, never the fragment endpoint url.
  - `it removes the control when there is no next page` asserts `el.remove()` after exhausting forward pages. This task keeps the element alive (it still owns the scroll-spy and the Load-previous button). Rewrite to assert the Load-MORE button (and `data-next`) is removed while the element itself stays connected.
  - Keep/adapt `it fetches the next page fragment` and `it appends the returned cards` (still valid, now routed through `loadFragment`/`appendCards`).
- The component currently relies on a template-provided `<button>`; the test harness manually appends one. With the new `data-role` buttons (Task 002) plus optional self-injection, ensure the component finds/binds buttons by `data-role`, and that button injection is idempotent across `connectedCallback` (avoid double-binding on reconnect).
- **Attribute contract (from Task 002):** element has `data-next` (maybe), `data-prev` (maybe), `data-canonical` (current/entry page), `data-grid` (`.catalog-product-grid`). **Button locations differ (per Task 002's pinned structure):** the **Load-MORE** button lives INSIDE the element (after the grid) — find via `this.querySelector('[data-role="load-more"]')`, or inject it if absent. The **Load-PREVIOUS** button is rendered as a SIBLING **before** `<mk-grid>` (it is NOT a child of this element) — find via `grid.parentElement?.querySelector('[data-role="load-previous"]')`. The component reads `data-prev`/`data-next`/`data-canonical` from ITSELF (the element), not from the buttons. Wire click handlers to forward/backward loaders. Button binding must be idempotent across reconnect.
- Forward (Load more): `loadFragment(data-next)` → `appendCards` → set element `data-next` to fragment's `data-next` (remove button + attr when null) → `scrollSpy.addBatch(fragment.canonical, firstAppendedCard)`.
- Backward (Load previous): `loadFragment(data-prev)` → `prependCardsAnchored` → set element `data-prev` to fragment's `data-prev` (remove the Load-previous button + attr when null = page 1 reached) → `scrollSpy.addBatch(fragment.canonical, firstPrependedCard)`.
- **URL**: instantiate `ScrollSpy` with `onChange = (url) => history.replaceState(null, '', url)`. Register the INITIAL batch from `data-canonical` + the grid's first card on connect. NEVER `pushState`, NEVER use the fragment endpoint URL for history.
- Remove the old `pushState(fragment-url)` behavior entirely.

## Requirements (Test Descriptions)
- [x] `it appends the next page and updates data-next when load more is clicked`
- [x] `it prepends the previous page anchored when load previous is clicked`
- [x] `it removes the load-previous button when page one is reached`
- [x] `it removes the load-more button when the last page is reached`
- [x] `it replaceStates the canonical full-page url (never the fragment endpoint url)`
- [x] `it updates the url to the topmost batch canonical on scroll`

## Acceptance Criteria
- Load more + Load previous both work via the shared loader; URL uses canonical `replaceState` + scroll-spy.
- The two obsolete existing tests (pushState assertion, remove-on-no-next) are rewritten to the new behavior; no stale assertions remain.
- vitest tests pass; no regression to forward append behavior.
- All requirements have passing tests.

## Implementation Notes

- Rewrote `mk-load-more.ts` to import `loadFragment`, `appendCards`, `prependCardsAnchored`, `ScrollSpy` from `./pagination-loader`.
- `ScrollSpy` is instantiated on first `connectedCallback` (idempotent via `#scrollSpy !== null` guard), registers initial batch from `data-canonical` + grid's first card.
- Load-more button found via `[data-role="load-more"]` inside the element; load-previous button found via `grid.parentElement?.querySelector('[data-role="load-previous"]')` (sibling pattern).
- Forward: `loadFragment(data-next)` → `appendCards` → update `data-next` or remove button+attr → `scrollSpy.addBatch`.
- Backward: `loadFragment(data-prev)` → `prependCardsAnchored` → update `data-prev` or remove button → `scrollSpy.addBatch`.
- History: `history.replaceState` only, never `pushState`. Fragment endpoint URLs never reach history.
- Rewrote two obsolete tests: pushState→replaceState, el.remove()→button removed+element stays.
- All 8 tests pass, no regressions in full suite (63 pre-existing failures unchanged).
