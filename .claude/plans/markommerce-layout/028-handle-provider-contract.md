# Task 028: `HandleProvider` contract + declaration in `Layout`

**Status**: completed
**Depends on**: 024
**Retry count**: 0

## Description
Introduce the `HandleProvider` contract — symmetric with `ContextProvider` — and a way for a layout file to declare that the runtime should ask one or more providers for additional active handles when this layout's route fires. Compile-time: the artifact records the providers tied to each base handle. Runtime wiring is task 029.

## Context
- Interface lives at `packages/layout/src/Contracts/HandleProvider.php`:
  ```php
  interface HandleProvider
  {
      /** @param array<string, mixed> $props @return list<string> */
      public function provide(array $props): array;
  }
  ```
- `ProvideHandle` value object lives at `packages/layout/src/ProvideHandle.php` in the `Markommerce\Layout\` namespace, mirroring the location and shape of `Provide`. It is `readonly` with two public properties: `string $provider` (class-string) and `array $props` (string → Source).
- Declaration on `Layout`: add a new field `handleProviders: list<ProvideHandle>` (default `[]`) to the `Layout` value object. This extends the changes made in task 024 — task 028 is the task that actually adds this field. Place it after `operations:` so existing named-arg callers stay compatible.
  ```php
  new ProvideHandle(
      provider: ConfigurableProductHandleProvider::class,
      props: ['product' => Source::context(ProductToken::class)],
  ),
  ```
- The `props:` array is resolved at request time by the same `SourceResolver` already used for context providers. A `HandleProvider` runs AFTER all `ContextProvider`s for the request, so it can read resolved context tokens.
- **Allowed Source types in `ProvideHandle::props`**: `RouteSource`, `QuerySource`, `ContextSource`, `ServiceSource`, and literal scalars. `ParentDataSource` and `IteratedSource` are nonsensical here (no parent placement, no iteration) and must be rejected at compile time with `InvalidSourceTypeException`.
- **Runtime ResolutionContext for handle providers**: a synthetic `ResolutionContext` is constructed with `request`, `routeParams`, `contextMap`, `container`, and a `placementChain` like `"$handleKey:handleProvider:$providerClass"`. `parentData` and `iterationItem` are `null`.
- Compiled artifact: each `PreparedTree` carries its declared `ProvideHandle` list as a sidecar so the runtime can invoke providers. Specifically:
  - Add `handleProviders: list<ProvideHandle>` (default `[]`) to `Markommerce\Layout\Cache\PreparedTree`.
  - Register a new `emitProvideHandle()` case in `PhpCodeEmitter` and call it from `emitPreparedTree()` for the new field. `ProvideHandle` is a closed value object — the emitter must support it explicitly or it will throw `UnsupportedValueException`.
  - Update `PreparedTreeBuilder` to copy `Layout::$handleProviders` into the built `PreparedTree`.
- Handle providers are NOT chained in v1 — but "chained" requires knowing what handle a provider returns. Task 028 enforces only the *local* rule: a `ProvideHandle::$provider` must be a class implementing `HandleProvider`. The cross-handle check (a provider's returned handle must not itself declare `handleProviders`) is enforced in task 030.

## Requirements (Test Descriptions)
- [ ] `it defines HandleProvider interface with provide method returning list of string`
- [ ] `it accepts a handleProviders list on Layout defaulting to empty`
- [ ] `it stores ProvideHandle value object at Markommerce\Layout\ProvideHandle with provider class-string and props map`
- [ ] `it adds handleProviders to PreparedTree defaulting to empty`
- [ ] `it serializes ProvideHandle entries into the compiled artifact via PhpCodeEmitter`
- [ ] `it round-trips a PreparedTree with handle providers through writer + reader`
- [ ] `it throws InvalidLayoutFileException when a provider class does not implement HandleProvider`
- [ ] `it throws InvalidSourceTypeException when ProvideHandle::props contains a ParentDataSource`
- [ ] `it throws InvalidSourceTypeException when ProvideHandle::props contains an IteratedSource`

## Acceptance Criteria
- All requirements have passing tests
- Existing layouts without `handleProviders` still compile and render unchanged
- PHPStan level 8 clean

## Implementation Notes
(Left blank — filled in during implementation)
