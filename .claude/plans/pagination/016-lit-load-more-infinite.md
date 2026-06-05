# Task 016: Lit load-more + infinite-scroll web components

**Status**: pending
**Depends on**: 015
**Retry count**: 0

## Description
Build the client-side enhancement: Lit web components that fetch the server-rendered page fragment (Task 015) and append it to the grid — a button-driven `mk-load-more` and a scroll-driven `mk-infinite-scroll` (IntersectionObserver) that degrades to a visible "Load more" button for accessibility and a reachable footer.

## Context
- Files: `packages/theme-blank/resources/js/components/mk-load-more.ts`, `mk-infinite-scroll.ts` (extend `MkElement`, register like `mk-grid.ts`); registered via the Vite entry `packages/theme-blank/resources/js/index.ts`.
- Behavior: read a `data-next` URL (the `nextPageUrl` rendered server-side), `fetch()` the fragment, append returned card markup into the grid container, update the address bar via the History API (`pushState` to the `?page=N` URL), and update/remove the control when there is no next page.
- `mk-infinite-scroll` observes a sentinel near the footer; when JS is unavailable the server-rendered crawlable `?page=` links remain the fallback.
- Progressive enhancement only: the page is fully functional (numbered links) without these components.

## Requirements (Test Descriptions)
- [x] `it fetches the next page fragment from the data-next url`
- [x] `it appends the returned cards to the existing grid`
- [x] `it updates the browser url to the next page via history api`
- [x] `it removes the control when there is no next page`
- [x] `it triggers a load when the sentinel intersects the viewport` (infinite)
- [x] `it exposes an accessible load-more fallback button` (infinite)

## Acceptance Criteria
- Components build cleanly under Vite and register as custom elements.
- Behavior covered by co-located vitest `*.test.ts` files (a vitest runner + `happy-dom` ARE configured in this repo — see `vite.config.ts` `test` block and existing `mk-*.test.ts` files; use `fetch`/IntersectionObserver mocks). JS tests are REQUIRED, not optional.
- The new components are registered via `packages/theme-blank/resources/js/index.ts` (the markommerce extension entry) so they load in the built bundle.
- No regression to the server-rendered fallback.

## Implementation Notes
- A vitest runner with `happy-dom` exists (confirmed: `node_modules/vitest`, `node_modules/happy-dom`, root `vite.config.ts`, and many co-located `mk-*.test.ts`). Mirror an existing component test (e.g. `mk-grid.test.ts`) for setup. Mock `fetch` and `IntersectionObserver`/`history.pushState`.
