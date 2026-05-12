# Devil's Advocate Review: catalog-basics (re-review)

Reviewed against the current plan structure (18 tasks, money split into interface + driver, no handwritten SQL migrations, currency-unaware Product, no service-level `*OrFail`). Earlier review file content is now obsolete and fully replaced.

## Critical (Must fix before building)

### C1. Driver-package namespace `Markommerce\MoneyMoneyphp\` violates the established Marko driver precedent
- Verified in `/home/michal/www/marko/marko/packages/database-mysql/composer.json`: the MySQL driver package is `marko/database-mysql` but its namespace is `Marko\Database\MySql\` (nested under the contract package's namespace). Its module bindings file uses `Marko\Database\MySql\Connection\MySqlConnection`, `Marko\Database\MySql\Introspection\MySqlIntrospector`, etc.
- Tasks 003, 004, 017, 018 all use `Markommerce\MoneyMoneyphp\` (a flat, non-nested kebab-to-pascal conversion). This is inconsistent with the only existing Marko driver precedent and will confuse anyone porting the pattern.
- Fix: switch to `Markommerce\Money\Moneyphp\` for the driver package namespace, with classes `Markommerce\Money\Moneyphp\Money`, `Markommerce\Money\Moneyphp\CurrencyConfig`, `Markommerce\Money\Moneyphp\MoneyFactory`. Package name stays `markommerce/money-moneyphp`. Update tasks 003, 004, 017, 018, and `_plan.md` Architecture Notes.

### C2. Task 011 — schema integration test cannot run without a database driver, but catalog's composer.json does not require one
- Task 011 says the `SchemaIntegrationTest` "boots a real Marko app context against the test database" and runs `db:migrate`. To do that it needs a concrete `ConnectionInterface` binding — provided by `marko/database-mysql` (or another driver). `marko/database` exposes only the interface.
- Task 005 currently requires `marko/database` and `marko/core` but not `marko/database-mysql` (neither in `require` nor `require-dev`).
- The CI command is `composer test:all` which runs integration-destructive tests. If the driver is not declared as a dev dependency of the catalog package, the test will fatal at boot when no `ConnectionInterface` is bound, and CI will fail mysteriously.
- Fix: add `marko/database-mysql: self.version` to the `require-dev` section of `packages/catalog/composer.json` (task 005). Update task 011's acceptance criteria to mention that the integration test relies on this dev dep and the test database connection configured at the monorepo root.

### C3. Task 015 depends on task 002 (`MoneyException`) but the service never uses it
- Per the plan, `ProductService::create` validates currency BEFORE constructing the entity and throws `InvalidProductDataException::currencyMismatch` (factory in task 009). It never imports nor catches `MoneyException`. The dependency on task 002 is spurious.
- Spurious dependencies inflate critical-path length for the parallel scheduler — task 015 is blocked waiting on a task it does not actually consume.
- Fix: remove `002` from task 015's `Depends on` list. Resulting deps: `001, 009, 011, 012`.

## Important (Should fix before building)

### I1. FakeMoney burden — `MoneyInterface` has 11 methods; service tests only need `amount()` and `currency()`
- Task 005's tests/Support list includes `FakeMoney`. `MoneyInterface` exposes amount, currency, add, subtract, multiply, allocate, equals, greaterThan, lessThan, isZero, format. A faithful `FakeMoney` is a non-trivial class — but `ProductService` tests only ever read `$basePrice->amount()` and `$basePrice->currency()`; `ProductPriceService` tests only need the same plus what the factory returns.
- Risk: a heavyweight shared `FakeMoney` becomes a parallel implementation of `Money` arithmetic. If task 013/015 worker writes naïve arithmetic in it, downstream tests may diverge from real Money behaviour.
- Fix: in task 005's scaffold notes (and re-affirmed in 013/015), specify that `FakeMoney` is a minimal stub implementing the interface where un-needed methods (`add`, `subtract`, `multiply`, `allocate`, `greaterThan`, `lessThan`, `format`) throw `LogicException('not implemented in FakeMoney')`. Only `amount()`, `currency()`, `equals()`, and `isZero()` carry real behaviour. Document this in the `_plan.md` Shared Test Fakes section.

### I2. Task 004 — `MoneyFactory::create` must resolve the default currency at call time, not cache it at construction
- Today's `CurrencyConfig` returns the constant `'USD'` so the bug cannot manifest. But the moment the stores/config module rebinds `CurrencyConfigInterface` to a request-scoped resolver (which is the explicit reason `CurrencyConfigInterface` exists), a `MoneyFactory` that snapshots the default at construction time would silently use stale data for the rest of the request lifecycle.
- Task 004's text says "when `$currency` is null, resolves via `$this->currencyConfig->getDefault()`" — which is correct — but the wording is ambiguous enough that a worker could read it as "construct once". Tests do not enforce the per-call resolution either.
- Fix: add a test bullet to task 004: `it calls CurrencyConfig::getDefault every time create is invoked with a null currency, not once at construction`. Use the `FakeCurrencyConfig` to make the return value mutate between calls and assert both returned `Money` instances carry the mutated currency.

### I3. `MoneyFactoryInterface` contract does not bind it to `CurrencyConfigInterface`; document this seam
- The interface signature is `create(int $amount, ?string $currency = null): MoneyInterface`. The "use the configured default when null" behaviour is a property of the **driver**, not the contract. Two driver implementations could legitimately resolve the default differently (env var, store scope, hardcoded). The plan's Architecture Notes hint at this but the interface-level tests in task 001 can only assert the method signature, not the default behaviour.
- Fix: add a one-line clarification in task 001's Context: "the contract's `?string $currency = null` semantics are intentionally driver-defined — assertions about the default-resolution behaviour live in the driver's test suite (task 004), not the interface's."

### I4. Task 008 — `Product` field `basePriceAmount` type for the `#[Column]` attribute
- Task 008 says `#[Column(type: 'bigint')]`. The Marko schema-builder convention (verified in `Marko\Database\Tests\Feature\EntityToMigrationWorkflowTest` — `#[Column(type: 'TEXT')]` uppercased) does not enforce case, but downstream SQL generators may be case-sensitive on the type string. The admin-auth migrations use `BIGINT` uppercase in SQL. Lowercase `bigint` in the attribute may produce inconsistent column types across MySQL/Postgres drivers.
- Fix: change task 008's bullet to `#[Column(type: 'BIGINT')]` (uppercase) to match the schema-generator's documented usage in `EntityToMigrationWorkflowTest`. Verify against `Marko\Database\Schema\Column::$type` casing.

### I5. Task 016 — `CategoryAssignmentService` SQL `SELECT 1 ... FOR UPDATE` and pivot column names
- The plan writes raw SQL referencing `product_categories(product_id, category_id)`. Task 007's `ProductCategory` entity uses property names `productId`/`categoryId`. The Marko schema generator converts camelCase to snake_case for column names (verified by reading `PropertyMetadata` and admin-auth's `RolePermission` precedent where `roleId` becomes `role_id`). Good — but the service worker must NOT use camelCase in SQL.
- Less obvious: the `FOR UPDATE` clause is MySQL-flavored. On SQLite (which Marko's test infrastructure sometimes uses), `FOR UPDATE` is a no-op syntax error in some configurations. Since the CI uses MySQL via `marko/database-mysql`, this is acceptable, but it is a hidden driver coupling at the service layer.
- Fix: add a one-line note in task 016 Context: "SQL is MySQL-flavored (catalog targets MySQL via `marko/database-mysql`); when introducing a portable abstraction, route through `QueryBuilderInterface` instead." This is a documentation fix only; no code change needed for this plan.

### I6. Task 017 — bindings test should require both money interfaces to be discoverable in the container
- Task 017's module.php only registers catalog's own bindings. Catalog's services need `CurrencyConfigInterface` and `MoneyFactoryInterface`, which come from `markommerce/money-moneyphp`'s `module.php`. If those packages are not installed, container resolution at runtime will fail with a generic "binding not found" error.
- Fix: extend task 017's bindings test to assert that the bound concrete classes' constructor type hints can all be resolved against the union of catalog's bindings + the money driver's bindings. Concrete check: parse the catalog `module.php`, then `require` `packages/money-moneyphp/module.php`, merge the bindings, then iterate every concrete class and use Reflection to verify every constructor parameter type is either (a) in the merged bindings list, (b) a generic Marko infrastructure type the container resolves itself (`ConnectionInterface`, `EntityMetadataFactory`, `EntityHydrator`, `EventDispatcherInterface`, `TransactionInterface`), or (c) nullable. This catches "I forgot to bind X" before runtime.

### I7. Task 005 — `FakeMoney` is listed in the tests/Support scaffolding but no task explicitly creates it
- Tasks 013 and 015 use `FakeMoney` / `FakeMoneyFactory` but neither task is the listed creator. The pattern elsewhere (e.g. `FakeConnection`) names the creating task explicitly (task 011 creates `FakeConnection`).
- Fix: amend task 013 to explicitly state it creates `FakeMoney` and `FakeMoneyFactory` in `tests/Support/`. Other tasks consume.

### I8. Task 011 — `findBySku` unit test bootstrap conflicts with schema-integration-test bootstrap
- Task 011 mixes two very different test layers: (1) unit tests for `findBySku` with a `FakeConnection`, and (2) an `integration-destructive` schema-integration test that boots a real DB. Workers can confuse the two test layers and end up writing the unit tests with a real DB or the integration test against the fake.
- Fix: explicitly split task 011's "Acceptance Criteria" into two sub-sections: "Unit test (no DB)" and "Integration test (real DB, `integration-destructive` group)". Each lists its own file path and its own bootstrap.

### I9. `_plan.md` Architecture Notes — `markommerce/money-moneyphp` namespace listed as `Markommerce\MoneyMoneyphp`
- Lines 124–127 of `_plan.md` use the wrong namespace per C1. Must be updated to `Markommerce\Money\Moneyphp\…` alongside the task fixes.

### I10. `_plan.md` Architecture Notes — Risks section references `markommerce/core`'s Money implementation
- Line 170 says "no direct `moneyphp/money` use outside `markommerce/core`'s Money implementation". After the restructure, the Money implementation lives in `markommerce/money-moneyphp`, not `markommerce/core`. This stale wording will mislead reviewers and the bindings-test author in task 017.
- Fix: update line 170 to reference `markommerce/money-moneyphp` (the driver package).

## Minor (Nice to address — left for user decision)

### M1. `MoneyInterface::format(?string $locale)` and the interface-level `@throws` contract
- `format()` on the interface declares it returns a string. The default driver requires `ext-intl`. If a future driver is intl-free (e.g., a stub for environments without intl), `format()` may need to throw. The interface's `@throws MoneyException` covers arithmetic operations but not formatting. Consider adding `@throws \RuntimeException` on `format()` so drivers retain the option.

### M2. Task 016 — `unassign` event dispatch keyed on `execute()` affected-rows
- The unassign path dispatches `ProductRemovedFromCategory` only when `execute()` returns a non-zero affected-rows count. This is correct for a single DELETE, but `affected_rows` is driver-specific in edge cases (e.g., some Postgres configurations need `RETURNING` to know). Acceptable for now (MySQL only), worth flagging for the future portability work.

### M3. Task 013 — `ProductPriceService` returns `MoneyInterface` constructed with no currency — what if the user passes an explicit currency in `Product`'s data?
- The plan keeps Product currency-unaware, but a clever caller might want a price in a specific currency. Today's API forces the application-default. Fine for this plan, but document in the README that price-in-foreign-currency is the next plan's territory.

### M4. `marko/core: self.version` requirement in `markommerce/money`
- Task 001 requires `marko/core: self.version` so `MoneyException` (task 002) can extend `MarkoException`. That's correct, but it means the interface-only package transitively requires the marko-core framework. Hard to avoid given the exception standard, but worth noting in the README that "interface-only" means "no concrete logic", not "no framework".

### M5. Task 015 — currency-mismatch validation happens before "negative amount" validation
- `InvalidProductDataException::currencyMismatch` and `InvalidProductDataException::negativeBasePrice` could both fire for the same input. The current order (currency first, amount second) is fine but should be documented as the contractual order for test stability.

## Questions for the Team

### Q1. Should `marko/database-mysql` be a hard dev-dependency of `markommerce/catalog`, or should the schema-integration test live in the root monorepo's testsuite instead?
The schema integration test in task 011 is genuinely cross-cutting (it asserts the *generated* schema across three entities). Putting it under `packages/catalog/tests/Feature/` couples catalog's tests to a specific DB driver. Alternative: relocate the test to `markommerce/markommerce`'s root testsuite where the driver is already required by the root metapackage. This re-review applies the minimum-friction fix (dev-dep on catalog) but the root-suite alternative is cleaner.

### Q2. `FakeMoney` versus per-test anonymous classes
The plan introduces a shared `FakeMoney` in `tests/Support/`. An alternative is per-test anonymous classes (`new class implements MoneyInterface { public function amount(): int { return 1000; } ... }`). Per-test anonymous classes are lighter (only stub the methods that specific test exercises) but cost more boilerplate. The plan currently picks "shared fake"; flagged so the user can decide if they want to switch.

### Q3. Should `MoneyFactoryInterface` live in the driver instead of the interface package?
Architecture doc says interface packages export interfaces, exceptions, and value objects. `MoneyFactoryInterface` is a service contract, not a value object. It still belongs in the interface package (because catalog needs to depend on the contract without seeing the driver), but it is the first time markommerce ships a service-typed contract from an interface-only package. Confirm the pattern is intentional.

### Q4. `format()` semantics across drivers
With `ext-intl` as a hard requirement of the only ship-with-Marko driver, the interface's `format(?string $locale)` is effectively intl-locked. Consider whether the interface should narrow to `format(string $locale)` (no nullable) and let the driver default-resolve, or stay nullable and document the locale-fallback contract in the interface's PHPDoc.

### Q5. Two-Step `Marko\Database\MySql` precedent vs flat `Markommerce\MoneyMoneyphp`
Even after applying C1's fix (`Markommerce\Money\Moneyphp\…`), the question remains: should the driver's namespace include the hyphenated convention (`Moneyphp`) or just `Default` / something semantic? Marko's precedent (`MySql` for `database-mysql`) preserves the kebab-suffix verbatim, so `Moneyphp` is consistent. Decide if any of the future driver packages (e.g. `markommerce/payment-stripe`) will follow the same rule.
