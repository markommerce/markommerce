# Task 018: CLI — `config:set` + `config:unset`

**Status**: completed
**Depends on**: 009
**Retry count**: 0

## Description
Implement the two write-side CLI commands. `config:set <key> <value> [--scope=axis=value,…]` persists a global or per-scope override. `config:unset <key> [--scope=axis=value,…]` removes a global or per-scope override. Both reuse `ConfigWriter` and inherit its optimistic-locking behavior + write-time axis validation.

## Context
- Marko command API (see task 017 for full reference):
  - Interface: `Marko\Core\Command\CommandInterface`
  - Attribute: `Marko\Core\Attributes\Command`
  - I/O: `Marko\Core\Command\Input`, `Marko\Core\Command\Output`
  - Auto-discovery: classes with `#[Command]` are auto-registered — no `module.php` work
- File layout: `packages/config/src/Command/SetCommand.php`, `packages/config/src/Command/UnsetCommand.php` (or a single file per command — match marko/database convention)
- The `<value>` argument is a string from the shell; parse it according to the target property's declared type from `ConfigRegistry`. For ints/floats use `intval`/`floatval` with strict validation (reject trailing garbage). For bools accept `true|false|1|0|yes|no`. For arrays accept a JSON string (`'{"a":1}'`). For enums use `tryFrom(...)`. On parse failure throw a CLI-specific exception with the offending value + expected type.
- `config:set` without `--scope` writes the global value
- `config:set` with `--scope=locale=es` writes an override under that signature
- `config:unset` symmetric: without `--scope` clears the global; with `--scope` clears that specific override
- Handle `StaleConfigWriteException`: re-print message, exit non-zero — the CLI does NOT retry (the writer already retries 3 times internally)
- Handle `AxisNotDeclaredException`: print which axis isn't declared on the property, list which axes ARE declared, exit non-zero
- Confirm destructive operations? No interactive prompts in v1 — these commands are meant to be scriptable. A `--force` flag is not needed because the writer's behavior is fully idempotent and predictable.

## Requirements (Test Descriptions)
- [x] `it sets a global value via config:set when no --scope flag is provided`
- [x] `it sets a scoped override via config:set when --scope flag is provided`
- [x] `it parses the shell <value> argument according to the property's declared type`
- [x] `it rejects an unparseable value with a clear error citing the expected type`
- [x] `it clears the global value via config:unset without --scope`
- [x] `it clears a specific scoped override via config:unset with --scope`
- [x] `it exits non-zero on StaleConfigWriteException without retrying`
- [x] `it exits non-zero on AxisNotDeclaredException citing declared axes`

## Acceptance Criteria
- Both commands implement `Marko\Core\Command\CommandInterface` and carry `#[Command]` with names `config:set` and `config:unset`
- Tests instantiate commands directly with stubbed dependencies and invoke `execute(...)`
- PHPStan level 8 clean

## Implementation Notes
(Left blank — filled in by programmer)
