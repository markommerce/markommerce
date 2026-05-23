# Task 017: CLI — `config:list` + `config:get`

**Status**: pending
**Depends on**: 010
**Retry count**: 0

## Description
Implement the two read-side CLI commands. `config:list` prints every registered config key (with axes and source class). `config:get <key> [--scope=axis=value,axis2=value2]` reads one config value either globally or under a specific scope signature.

## Context
- Marko has a real command framework. Conform to its API exactly:
  - Interface: `Marko\Core\Command\CommandInterface` (single method `execute(Input $input, Output $output): int`)
  - Attribute: `Marko\Core\Attributes\Command(name: 'config:list', description: '...', aliases: [])`
  - I/O: `Marko\Core\Command\Input` and `Marko\Core\Command\Output`
  - Auto-discovery: classes with `#[Command]` under any module's `src/` directory are auto-registered by `Marko\Core\Command\CommandDiscovery`. NO explicit registration in `module.php` is needed.
  - Reference implementations: `marko/database/src/Command/MigrateCommand.php`, `marko/queue/src/Command/*`, `marko/page-cache/src/Command/*`. Match their shape (constructor DI, `readonly class`, integer exit code, `Output::writeLine()` for output).
- File layout: place commands in `packages/config/src/Command/` (singular `Command/`, matching `marko/database` and `marko/queue` conventions).
- Output format: human-readable text by default. Add `--format=json` for machine-readable output (used by future admin UIs).
- `config:get` without `--scope` resolves under an empty `ScopeContext` (returns global or default).
- `config:get` with `--scope` builds a `ScopeSignature` from the comma-separated `axis=value` pairs. Add a `ConfigResolver::resolvedAt(string $configClass, string $field, ScopeContext $context): mixed` overload that takes a synthetic context (a fresh `ScopeContext` populated from the signature) and uses it instead of the live context. Implementer must NOT mutate the live `ScopeContext` because the CLI process may invoke multiple commands in one process during tests.
- Both commands handle `ConfigNotFoundException` gracefully — exit with a non-zero status and a "did you mean…?" hint listing the 3 closest registered keys (use `levenshtein()` for the proximity search).

## Requirements (Test Descriptions)
- [ ] `it lists every registered config key with its source class and axes in human-readable output`
- [ ] `it lists every registered config key as JSON when --format=json is given`
- [ ] `it returns the resolved global value for a key under an empty scope`
- [ ] `it returns the resolved scoped value for a key when --scope is given`
- [ ] `it exits non-zero with a did-you-mean hint when the key is unknown`
- [ ] `it does not print decrypted plaintext when the config is #[Config(secret: true)] — instead shows a redacted marker like ***`

## Acceptance Criteria
- Both commands implement `Marko\Core\Command\CommandInterface` and carry the `#[Command]` attribute with names `config:list` and `config:get`
- Tests invoke commands by instantiating the command class directly with stubbed dependencies and calling `execute(new Input([...]), new Output(...))` — no `exec()`, no shelling out
- PHPStan level 8 clean
- `@throws` tags accurate

## Implementation Notes
(Left blank — filled in by programmer)
