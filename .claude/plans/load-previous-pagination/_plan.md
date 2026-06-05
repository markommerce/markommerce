# Plan: Load-previous pagination (backward infinite-scroll / load-more)

## Created
2026-06-05

## Status
completed

## Objective
Let a shopper who enters the catalog listing on a page > 1 (e.g. `?page=10`) load EARLIER pages backward via a clickable "Load previous" control, in both `load_more` and `infinite` presentation modes — and fix the address-bar URL to track the real page (canonical, scroll-spy) instead of the broken fragment-endpoint URL.

## Related Issues
none

## Discovery Notes
Extends the already-shipped pagination feature (commit `e8ba61d` + uncommitted polish on the parent branch). Verified integration points:
- **Forward loading works**: `mk-load-more.ts` / `mk-infinite-scroll.ts` (Lit, `packages/theme-blank/resources/js/components/`) read `data-next` + `data-grid` and APPEND the fragment endpoint's cards into `.catalog-product-grid`. Fragment endpoint: `GET /catalog/category/{id}/page?page=N` (`CategoryController::pageFragment` + `category_page_fragment.php` + `product-grid-fragment.latte`, which renders `<div class="catalog-product-grid-fragment" data-next="…">…cards…</div>`).
- `ProductGridComponent::data()` builds `nextPageUrl` = fragment URL for page+1; on `ProductGridData`. Offset pages implement `RandomAccessPageInterface` (currentPage/totalPages); offset is the catalog default.
- **Latent gap found**: `mk-load-more.ts` does `this.querySelector('button')` but the template renders NO button — so load-more mode has nothing to click today (only `mk-infinite-scroll` injects its own button). This plan gives `mk-load-more` its own buttons.
- **Bug to fix**: components call `history.pushState(…, <fragment-endpoint-url>)` → address bar shows `/catalog/category/1/page?page=7` and refresh renders the bare fragment (broken). Must use the canonical full-page URL + `replaceState`.
- `ScopedProductGridComponent` (catalog-storefront-scope) mirrors `ProductGridComponent`'s `data()` signature — it must be kept in sync when `ProductGridData` gains fields.
- vitest + happy-dom configured (host). catalog-storefront CSS entry + Vite build input wired. Playground runs Vite dev mode.

### Resolved clarifications
1. **Load previous = button-only (clickable)** — no top auto-sentinel (avoids surprise backward-load on entry). Forward stays auto in `infinite`.
2. **URL = full scroll-spy** — the address bar continuously tracks the canonical page URL of the batch at the top of the viewport, both directions, via `replaceState`.

## Scope

### In Scope
- `ProductGridData` + `ProductGridComponent` (+ `ScopedProductGridComponent`): `previousPageUrl` (fragment URL for currentPage-1, only when random-access & currentPage > 1) and `canonicalPageUrl` (full-page `/catalog/category/{id}?page=N`, size/sort preserved).
- `product-grid-fragment.latte`: emit `data-prev` + `data-canonical` on the fragment wrapper.
- `product-grid.latte`: pass `data-prev` + `data-canonical` to the `mk-*` elements; render a "Load previous" button above the grid when a previous page exists; ensure `mk-load-more` has a "Load more" button.
- Shared JS loader/scroll-spy module + updates to `mk-load-more.ts` and `mk-infinite-scroll.ts`: prepend-with-scroll-anchoring, backward `data-prev` chaining, button-only previous, `replaceState` to canonical, full scroll-spy.
- CSS: style the "Load previous" button.

### Out of Scope
- **Keyset** (sequential) backward loading — keyset has no random-access page numbers; document the limitation. Offset only (the default).
- Bidirectional auto-sentinel for previous (button-only per decision).
- Changing the numbered/`:defined`-hidden fallback behavior (keep as-is).

## Success Criteria
- [ ] Entering on `?page=N` (N>1) in load_more/infinite shows a "Load previous" button that prepends page N-1 without the viewport jumping (scroll anchored), chaining down to page 1 then disappearing.
- [ ] Forward load-more/infinite still works (no regression); `mk-load-more` now has a working button.
- [ ] Address bar shows the canonical `/catalog/category/{id}?page=N` (never the `/page` fragment URL), via `replaceState`; refresh loads a real page; scroll-spy updates it both directions.
- [ ] All PHP (Pest) + JS (vitest) tests pass; PHPStan level 8 clean; phpcs/php-cs-fixer clean.
- [ ] Code follows project standards.

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | ProductGridData/Component: previousPageUrl + canonicalPageUrl | - | completed |
| 002 | Fragment + grid templates: data-prev/data-canonical + Load-previous & Load-more buttons | 001 | pending |
| 003 | Shared JS pagination loader + scroll-spy module | - | completed |
| 004 | mk-load-more: forward + backward buttons, scroll-spy, canonical replaceState | 003 | pending |
| 005 | mk-infinite-scroll: backward button + scroll-spy + canonical fix (keep forward auto) | 003 | pending |
| 006 | CSS: style the Load-previous button | 002 | pending |

## Architecture Notes
- **Attribute contract (pin so PHP templates and JS agree without serializing tasks):** the `mk-load-more` / `mk-infinite-scroll` element carries `data-next` (fragment URL for next page, or absent at last), `data-prev` (fragment URL for prev page, or absent at page 1), `data-canonical` (canonical full-page URL of the CURRENT/entry page), `data-grid` (`.catalog-product-grid`). The fetched fragment wrapper (`.catalog-product-grid-fragment`) carries `data-next`, `data-prev`, and `data-canonical` for the page it represents.
- **Scroll container = window/document** (the storefront scrolls the document). Scroll anchoring on prepend: capture `document.scrollingElement.scrollTop` + `scrollHeight` before insert, restore `scrollTop += (newScrollHeight - oldScrollHeight)` after. Scroll-spy: throttle a scroll handler with `requestAnimationFrame`.
- **Scroll-spy batch model (no DOM wrappers — cards stay direct grid children so `mk-grid` layout is preserved):** JS keeps an ordered list of batches `{ canonicalUrl, firstCardEl }`. Initial batch = `{ data-canonical, grid.firstElementChild }`. Append → new batch's `firstCardEl` = first appended card. Prepend → first prepended card. On scroll, pick the batch whose `firstCardEl` is the topmost one at/above the viewport top and `replaceState` its `canonicalUrl`.
- **Shared module** (`packages/theme-blank/resources/js/components/pagination-loader.ts` or similar): `loadFragment(url) → { cards: Node[], next: string|null, prev: string|null, canonical: string|null }`; `appendCards(grid, cards)`; `prependCardsAnchored(grid, cards)`; and a `ScrollSpy` class (`addBatch`, `start`, `stop`). Both components import it; no logic duplicated.
- **Previous is button-only**: components render/own a "Load previous" button when `data-prev` is present; clicking prepends + anchors + updates `data-prev` to the fetched fragment's own `data-prev`; remove the button (not the element) when no `data-prev` (page 1 reached). NO top sentinel. **DOM caveat:** `<mk-grid class="catalog-product-grid">` is a SIBLING above the `mk-*` pagination element, so the button is not automatically "above the grid" — Task 002 pins the exact structure (keep in `mk-*` + CSS reposition, or render before `<mk-grid>`); 004/005/006 build against that.
- `mk-load-more` injects its own "Load more" + "Load previous" buttons (fixing the missing-button gap); `mk-infinite-scroll` keeps the bottom sentinel for forward auto-load and adds only the "Load previous" button.

## Risks & Mitigations
- **Scroll anchoring jank** on prepend: mitigate by adjusting `scrollTop` synchronously right after DOM insert (before paint); cover with a vitest test asserting scrollTop compensation.
- **Scroll-spy firing on every scroll event**: throttle via `requestAnimationFrame`; only `replaceState` when the computed page actually changes (avoid history churn).
- **JS/PHP attribute drift**: attribute names pinned in Architecture Notes; both Task 002 (templates) and 004/005 (JS) reference the same contract, so they can run without a hard dependency.
- **`ScopedProductGridComponent` SILENTLY DROPS new fields** (not just "breaks"): it re-constructs `ProductGridData` arg-by-arg and is the active `#[Preference]` in scoped installs. Task 001 MUST forward `previousPageUrl`/`canonicalPageUrl` in that re-construction or the feature is dead under scope. Covered by a new scope-forwarding test.
- **Latte undefined-variable errors in existing tests**: the `PresentationSwitchTest` helper renders `product-grid.latte` without the new vars. Task 002 references them with `?? null` guards (matching the existing `$nextPageUrl ?? null` style) so the existing tests pass unchanged.
- **Obsolete JS tests break**: `mk-load-more.test.ts` asserts `pushState` and `el.remove()` — both behaviors are removed. Task 004 rewrites those two tests to the new `replaceState`-canonical + element-stays-alive behavior.
- **happy-dom has no layout**: scroll anchoring + scroll-spy depend on `scrollHeight`/`scrollTop`/`getBoundingClientRect`, all 0 in happy-dom. Tasks 003/004/005 stub these explicitly; the shared API is shaped so the metrics are injectable.
- **Load-previous button position**: the grid is a sibling rendered above the pagination element, so "above the grid" needs a deliberate DOM/CSS choice. Task 002 pins the structure in its Implementation Notes; Task 006 styles to match.
- **Branch base**: `feature/load-previous-pagination` off `feature/pagination` (carries the uncommitted pagination-polish working tree); PR targets `develop`.
