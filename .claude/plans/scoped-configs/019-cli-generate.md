# Task 019: CLI — `config:generate`

**Status**: completed
**Depends on**: 014, 015
**Retry count**: 0

## Description
Implement the `config:generate` command. It walks the `ConfigRegistry`, expands the class list via `PreferenceAwareScanner`, calls `ProxyGenerator` for each, and writes the proxy files via `ProxyWriter`. Designed to be safely re-runnable (idempotent) and CI-friendly (non-zero exit on any failure, summarizes what changed).

## Context
- Marko command API: `Marko\Core\Command\CommandInterface`, `#[Marko\Core\Attributes\Command(name: 'config:generate', …)]`, `Input`/`Output`. Auto-discovered — no `module.php` registration.
- File: `packages/config/src/Command/GenerateCommand.php`
- Idempotency: clear the target directory at the start, then write fresh files — guarantees no stale proxies linger after a config class is renamed or removed
- Command output (non-`--format=json` mode): one line per generated proxy with a check mark, plus a summary footer (`Generated N proxies in <dir>`)
- Non-zero exit on:
  - Any `InvalidConfigClassException` thrown by `ProxyGenerator` (with the property/class that broke)
  - Filesystem write failure (`ProxyWriter` throws → bubble up)
  - Empty registry: warn (exit 0 with a message) — empty is valid, not an error
- This command will run from CI before PHPStan, per the plan's CI policy

## Requirements (Test Descriptions)
- [x] `it generates a proxy file for every registered config class`
- [x] `it expands the class list via PreferenceAwareScanner so preferenced subclasses also get proxies`
- [x] `it clears the target directory before writing new proxies`
- [x] `it writes proxy files at FQN-mirroring paths under the target directory`
- [x] `it prints a summary line counting generated proxies`
- [x] `it exits non-zero when ProxyGenerator throws InvalidConfigClassException`
- [x] `it warns and exits zero when the registry is empty`

## Acceptance Criteria
- The command is safely re-runnable with no side effects between identical runs
- Output is grep-able (one proxy per line) for CI parsing
- PHPStan level 8 clean

## Implementation Notes
(Left blank — filled in by programmer)
