# Task 014: CLI Command `layout:compile`

**Status**: completed
**Depends on**: 010, 011
**Retry count**: 0

## Description
Create the `layout:compile` CLI command that wires the full compiler pipeline together: discovery → resolution → validation → artifact write. This is the assembly point — it constructs and runs every prior compiler stage and is the command CI invokes.

## Context
- IMPORTANT: the `#[Command]` attribute and `CommandInterface` live in **`marko/core`** (`Marko\Core\Attributes\Command`, `Marko\Core\Command\CommandInterface`, `Marko\Core\Command\Input`, `Marko\Core\Command\Output`) — NOT in `marko/cli`. `marko/cli` only provides the `bin/marko` entrypoint and `CliKernel`. The package already depends on `marko/core` (task 001), so no extra dependency is needed; `marko/cli` is the runner, not the source of the attribute.
- Implement as a class with `#[Command(name: 'layout:compile', description: ...)]` implementing `Marko\Core\Command\CommandInterface` (`execute(Input $input, Output $output): int`).
- Commands are auto-discovered by `Marko\Core\Command\CommandDiscovery`, which scans each module's **`src/`** directory only. The command class MUST live under `packages/layout/src/Command/` (not the `layout/` discovery directory) and the file must be loadable by reflection. `CommandDiscovery` also requires the class to have an `execute` method and implement `CommandInterface` or it throws `CommandException`.
- Pipeline: run discovery (task 008) → resolution (task 009) → validation (task 010) → artifact writer (task 011) writing `var/cache/markommerce/layouts.php`.
- Extract the discovery→resolution→validation→write sequence into a reusable `Compiler` service so task 015's `CompileIfStaleMiddleware` can call the exact same pipeline. The command is a thin wrapper that invokes the service and formats output.
- On success: print a summary (N layouts, N extensions applied, N handles compiled, artifact path) and return exit code 0.
- On any compile error (any layout exception from task 002): catch it, print `message`, `context`, and `suggestion` in a clearly formatted block, and return a non-zero exit code. Do NOT write a partial artifact on failure.
- Provide a `--quiet` or equivalent flag so CI can run it without noisy output (optional — default if `Input` supports flags easily; skip if it complicates the task).
- Add a test/CI hook: `composer test` (or the CI config) should run `layout:compile` so compile errors fail the build at PR time, not deploy time. If wiring this into `composer.json` scripts is straightforward, do it; otherwise document the exact command for the user to add and note it in Implementation Notes.
- Patterns to follow: `marko/dev-server/src/Command/DevDownCommand.php` for the exact command structure (`#[Command]` from `Marko\Core\Attributes\Command`, `implements CommandInterface`, `execute(Input, Output): int`).

## Requirements (Test Descriptions)
- [x] `it registers a layout:compile command`
- [x] `it compiles all discovered layouts into the artifact file`
- [x] `it returns exit code zero on a successful compile`
- [x] `it prints a summary of compiled handles on success`
- [x] `it returns a non-zero exit code when a layout fails validation`
- [x] `it prints the message, context and suggestion of a compile error`
- [x] `it does not write an artifact when compilation fails`

## Acceptance Criteria
- All requirements have passing tests
- Command class lives in `src/Command/`, carries `Marko\Core\Attributes\Command`, implements `Marko\Core\Command\CommandInterface`, and is auto-discovered by `Marko\Core\Command\CommandDiscovery`
- The compile pipeline is extracted into a reusable `Compiler` service (shared with task 015)
- Compile errors produce non-zero exit + formatted error, no partial artifact
- CI hook for `layout:compile` wired or documented in Implementation Notes

## Implementation Notes
- Created `CompilerInterface` (`src/Compiler/CompilerInterface.php`) — single `compile(): array<string, PreparedTree>` method
- Created `Compiler` service (`src/Compiler/Compiler.php`) implementing `CompilerInterface` — wires `LayoutDiscovery → ResolutionPhase → ValidationPhase → PreparedTreeBuilder`
- Created `ArtifactWriterInterface` (`src/Cache/ArtifactWriterInterface.php`) — `write(array $trees): void` + `getPath(): string`
- Updated `ArtifactWriter` to implement `ArtifactWriterInterface` and added `getPath()` method
- Created `CompileCommand` (`src/Command/CompileCommand.php`) — `#[Command(name: 'layout:compile')]`, injects `CompilerInterface` + `ArtifactWriterInterface`, catches `LayoutException` and prints formatted error block, returns exit code 1 on failure
- Tests use `FakeCompiler implements CompilerInterface` and `FakeArtifactWriter implements ArtifactWriterInterface` for full unit isolation
- CI hook: run `bin/marko layout:compile` before or after tests to validate compile; not wired into `composer test` since `composer test` runs unit tests only — add `bin/marko layout:compile` as a separate CI step
