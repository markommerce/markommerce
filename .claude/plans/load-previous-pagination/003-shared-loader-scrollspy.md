# Task 003: Shared JS pagination loader + scroll-spy module

**Status**: complete
**Depends on**: none
**Retry count**: 0

## Description
Extract the fragment-loading, card append/prepend (with scroll anchoring), and scroll-spy logic into one shared TypeScript module that both `mk-load-more` and `mk-infinite-scroll` consume — so backward/forward loading and URL tracking aren't duplicated.

## Context
- File: `packages/theme-blank/resources/js/components/pagination-loader.ts` (new). TypeScript/Lit-adjacent (no Lit needed here — plain DOM helpers). Match existing TS style.
- vitest + happy-dom on the HOST: `npx vitest run packages/theme-blank/resources/js/components/pagination-loader.test.ts` (NOT in the PHP container).
- API to export:
  - `loadFragment(url: string): Promise<{ cards: Node[]; next: string | null; prev: string | null; canonical: string | null }>` — `fetch(url)`, parse the returned HTML, find `.catalog-product-grid-fragment`, return its child nodes (clone), plus its `data-next`/`data-prev`/`data-canonical` attributes. Returns empty cards if the wrapper is missing.
  - `appendCards(grid: Element, cards: Node[]): void` — append clones to the grid.
  - `prependCardsAnchored(grid: Element, cards: Node[], scroller?: Element): void` — capture `scroller.scrollTop` + `scroller.scrollHeight` (default scroller = `document.scrollingElement`), insert clones before the grid's first child, then set `scroller.scrollTop += (newScrollHeight - oldScrollHeight)` so the viewport does NOT jump.
  - `class ScrollSpy` — `constructor(scroller?, onChange: (canonicalUrl: string) => void)`; `addBatch(canonicalUrl: string, firstCardEl: Element)`; `start()` / `stop()` (rAF-throttled scroll listener). On scroll, pick the batch whose `firstCardEl` is the topmost one at/above the viewport top; call `onChange(canonicalUrl)` ONLY when the selected canonical changes (no churn).
- Scroll container default = `document.scrollingElement` (the storefront scrolls the document).
- **happy-dom does NO layout** — `scrollHeight`, `scrollTop`, and `Element.getBoundingClientRect()` all return 0/empty by default and won't reflect real geometry. The scroll-anchoring and scroll-spy logic is therefore only testable by INJECTING fake metrics. Design the API so this is possible:
  - `prependCardsAnchored` reads `scroller.scrollHeight` and writes `scroller.scrollTop` — in tests, pass a fake `scroller` object/stub (e.g. an element with overridden `scrollHeight` getter via `Object.defineProperty`, and a writable `scrollTop`) and assert the delta math. Do NOT rely on real layout.
  - `ScrollSpy` decides "topmost batch" from `firstCardEl.getBoundingClientRect().top` (relative to viewport top). In tests, stub each card's `getBoundingClientRect` to return controlled `top` values. Make the comparison purely a function of `getBoundingClientRect().top` so it is unit-testable without layout.
  - rAF: stub `requestAnimationFrame` (or expose a synchronous "tick"/internal handler the test can call directly) so the throttled handler is deterministic in vitest.
- Keep the geometry source points (which property is read for anchoring, which for spy selection) explicit and minimal so the stubs in the test are obvious.

## Requirements (Test Descriptions)
- [x] `it parses cards, next, prev and canonical from a fragment response`
- [x] `it returns empty cards when the fragment wrapper is missing`
- [x] `it appends cloned cards to the grid`
- [x] `it prepends cards and compensates scrollTop so the viewport stays anchored` (use a stub scroller with a fake `scrollHeight` before/after insert; assert `scrollTop` increased by the height delta)
- [x] `it reports the topmost visible batch canonical via scroll spy` (stub each batch's `firstCardEl.getBoundingClientRect().top`)
- [x] `it only invokes the scroll-spy callback when the active page changes` (drive multiple scroll ticks; assert `onChange` fires once per actual canonical change, not per tick)

## Acceptance Criteria
- Module exports the documented API; both components can import it.
- Behavior covered by co-located vitest tests (mock `fetch`, scroll metrics).
- All requirements have passing tests.

## Implementation Notes
- Implemented `loadFragment`, `appendCards`, `prependCardsAnchored`, and `ScrollSpy` in `pagination-loader.ts`.
- `ScrollSpy` uses rAF-throttled scroll listener; tests stub `requestAnimationFrame` to synchronously invoke the tick.
- `prependCardsAnchored` reads `scrollHeight` before and after insert, adjusts `scrollTop` by the delta; tests use `Object.defineProperty` to supply controlled `scrollHeight` values.
- `ScrollSpy` picks the "topmost visible" batch as the one with largest `getBoundingClientRect().top` that is still <= 0; tests stub `getBoundingClientRect` per card element.
