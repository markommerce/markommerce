# Task 008: Reject Default-Scope Writes at the Storage Trait Level

**Status**: pending
**Depends on**: 001, 002
**Retry count**: 0

## Description
Polices the lowest write layer — the `HasScopes` storage trait itself — so direct `$entity->setOverride('locale:default', ...)` calls that bypass `ScopeResolver` (and therefore `ScopeSignatureValidator`) still fail loudly. Introduces `Markommerce\Scope\Storage\DefaultScopeGuard` (a class with static `configure()` / `reset()` / `assertWritable()` methods) that holds an `array<string, string>` of axis → default-scope mappings. The scope module's `module.php` `boot` callback populates the guard from `ScopeRegistryInterface` once at bootstrap. `HasScopes::setOverride()` and `HasScopes::clearOverride()` call `DefaultScopeGuard::assertWritable($signature)` which throws a new `ScopeStorageException::defaultScopeWrite(...)` on violation.

## Context

After task 005, `ScopeSignatureValidator` rejects default-scope signatures — but the validator is only invoked through `ScopeResolver::setOverride()` / `clearOverride()`. The `HasScopes` trait's own methods take a serialized signature string and write it to the JSONB column without any validation. After tasks 004 and 005 the read paths (`ScopeWalker::walk`/`walkAt` via the enumerator filter and the `findFirstMatch` filter) make any such direct-write override unreachable — so it's wasted bytes, not data corruption. But the user has chosen to enforce the invariant at the lowest layer.

**Architectural concession.** PHP traits cannot have constructors and CLAUDE.md forbids service locators, so the trait cannot pull `ScopeSignatureValidator` from a container at write time. The pragmatic solution is a small purpose-built class with static state that is *configured once* at module boot — configuration, not runtime container lookup. The `DefaultScopeGuard` class isolates this concession to a single named file and keeps the static state out of the trait.

### `DefaultScopeGuard` shape

```php
namespace Markommerce\Scope\Storage;

use Markommerce\Scope\Exceptions\ScopeStorageException;

class DefaultScopeGuard
{
    /** @var array<string, string> */
    private static array $axisDefaults = [];

    /** @param array<string, string> $axisDefaults */
    public static function configure(array $axisDefaults): void
    {
        self::$axisDefaults = $axisDefaults;
    }

    public static function reset(): void
    {
        self::$axisDefaults = [];
    }

    public static function isConfigured(): bool
    {
        return self::$axisDefaults !== [];
    }

    /** @throws ScopeStorageException */
    public static function assertWritable(string $signature): void
    {
        if (self::$axisDefaults === []) {
            return; // not configured — be lenient so unrelated unit tests don't have to wire it
        }

        foreach (explode('|', $signature) as $part) {
            $segments = explode(':', $part);
            if (count($segments) !== 2) {
                continue; // malformed signatures are someone else's problem (ScopeSignature::fromString)
            }
            [$axis, $value] = $segments;
            if (isset(self::$axisDefaults[$axis]) && self::$axisDefaults[$axis] === $value) {
                throw ScopeStorageException::defaultScopeWrite($axis, $value);
            }
        }
    }
}
```

The "lenient when not configured" branch is the test-isolation safety net: existing trait-using tests (`HasScopesTraitTest`, `ScopedOverridesPersistenceTest`, etc.) don't need to call `DefaultScopeGuard::configure(...)`. The integration test in this task exercises the configured path.

### Trait wiring

```php
public function setOverride(string $signature, string $property, mixed $value): void
{
    DefaultScopeGuard::assertWritable($signature);
    // existing logic
}

public function clearOverride(string $signature, string $property): void
{
    DefaultScopeGuard::assertWritable($signature);
    // existing logic
}
```

### Module boot

`packages/scope/module.php` gains a `boot` callback that snapshots the registry's axis defaults into the guard:

```php
'boot' => function (ContainerInterface $container): void {
    $registry = $container->get(ScopeRegistryInterface::class);
    $defaults = [];
    foreach ($registry->listAxes() as $axisName) {
        $defaults[$axisName] = $registry->getAxis($axisName)->default;
    }
    DefaultScopeGuard::configure($defaults);
},
```

The `@throws` PHPDoc for `setOverride`/`clearOverride` on the interface and trait gains `ScopeStorageException`.

- Files to create:
  - `packages/scope/src/Storage/DefaultScopeGuard.php`
  - `packages/scope/tests/Unit/Storage/DefaultScopeGuardTest.php`
- Files to modify:
  - `packages/scope/src/Storage/HasScopes.php` (trait — add the two `assertWritable` calls and the `@throws` tag)
  - `packages/scope/src/Storage/HasScopesInterface.php` (add `@throws ScopeStorageException` on `setOverride` / `clearOverride`)
  - `packages/scope/src/Exceptions/ScopeStorageException.php` (add `defaultScopeWrite(string $axis, string $defaultScope)` factory)
  - `packages/scope/module.php` (add the `boot` callback)
  - `packages/scope/tests/Unit/ModulePhpTest.php` (assert the `boot` key is present and invokes the registry; the test can construct a container fake and pass a stub `PhpScopeRegistry` with task 002's schema)
- Files to verify (touch only if newly red):
  - `packages/scope/tests/Unit/Storage/HasScopesTraitTest.php` (existing tests write at scopes like `'global'`, `'eu'`, `'es'` — none collide with the sentinel default; without calling `DefaultScopeGuard::configure()` these tests hit the lenient path and stay green)
  - `packages/scope/tests/Feature/ScopedOverridesPersistenceTest.php` (same reasoning)
  - `packages/scope/tests/Feature/ScopedOverridesEntityDirtyTrackingTest.php` (same reasoning)
- Test hygiene: every test in this task's new test files MUST call `DefaultScopeGuard::reset()` in an `afterEach()` hook to prevent static-state bleed across tests. Pest 4 parallel mode runs each test file in a separate process so cross-file bleed is not a concern, but within a file the static state must be cleaned.
- Patterns to follow:
  - Static factory methods on `MarkoException` subclasses with `message`/`context`/`suggestion`.
  - `@throws` tags on every method that throws or propagates (CLAUDE.md code standard #8).
  - Add the `'boot'` key after `'singletons'` in `module.php`, following the existing key order.

## Requirements (Test Descriptions)
- [ ] `it allows writes to non-default-scope signatures when configured with axis defaults`
- [ ] `it throws ScopeStorageException when HasScopes setOverride writes at a default-scope signature`
- [ ] `it throws ScopeStorageException when HasScopes clearOverride targets a default-scope signature`
- [ ] `it rejects a multi-axis signature that names any axis at its default`
- [ ] `it allows all writes when DefaultScopeGuard has not been configured`
- [ ] `it configures DefaultScopeGuard from the scope registry during module boot`

## Acceptance Criteria
- All requirements have passing tests.
- `DefaultScopeGuard::reset()` is called in every test's `afterEach()` hook in the new test file.
- The `ScopeStorageException::defaultScopeWrite` factory carries `message`, `context`, and `suggestion` that name the axis, the default-scope path, and tell the merchant to edit the entity's property directly.
- `HasScopesInterface` and `HasScopes` PHPDoc on `setOverride` / `clearOverride` lists `ScopeStorageException` in `@throws`.
- `composer test` for the `scope` package is green. **Do not gate on `composer test:all`** — `scope-pgsql`'s integration test stays red until task 007 lands.
- `phpstan analyse` clean.
- Code follows code standards.

## Implementation Notes
(Left blank - filled in by programmer during implementation)
