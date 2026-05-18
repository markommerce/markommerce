# Task 002: Add `requireInnerControl()` Helper to `@markommerce/frontend`

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Add a `requireInnerControl(element, selector)` utility function to `packages/frontend/resources/js/` that logs `console.warn` once per element instance when the inner native form control is missing. Export it from the frontend package index. Every Phase 3 form wrapper component calls this in `connectedCallback`.

## Context
- Related files:
  - `packages/frontend/resources/js/index.ts` — add export
  - New file: `packages/frontend/resources/js/requireInnerControl.ts`
  - New test: `packages/frontend/resources/js/requireInnerControl.test.ts`
- Patterns to follow: `MkElement.ts` and `registry.ts` in the same directory for file structure and export patterns.
- The function must only warn once per element instance (not on every call). Use a `WeakSet` to track warned elements.

## Implementation Shape

```typescript
// requireInnerControl.ts
const warned = new WeakSet<HTMLElement>();

export function requireInnerControl(
  element: HTMLElement,
  selector: string,
): Element | null {
  const found = element.querySelector(selector);
  if (!found && !warned.has(element)) {
    warned.add(element);
    console.warn(
      `[${element.tagName.toLowerCase()}] expected a child matching "${selector}" but found none.`,
    );
  }
  return found;
}
```

## Requirements (Test Descriptions)

- [ ] `it is exported from @markommerce/frontend index`
- [ ] `it returns the matching child element when the selector finds a descendant`
- [ ] `it returns null when no descendant matches the selector`
- [ ] `it calls console.warn exactly once when no matching descendant exists`
- [ ] `it does not call console.warn on a second call for the same element instance`
- [ ] `it calls console.warn again for a different element instance with no matching descendant`
- [ ] `it does not call console.warn when a matching descendant is present`
- [ ] `it includes the element tag name in the warning message`
- [ ] `it includes the selector string in the warning message`

## Acceptance Criteria
- All requirements have passing tests
- `requireInnerControl` exported from `packages/frontend/resources/js/index.ts`
- Uses `WeakSet` (not a `Map` or `Set`) so element instances are GC-able
- No external dependencies added
- TypeScript compiles without errors
