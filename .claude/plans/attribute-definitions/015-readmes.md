# Task 015: Package READMEs

**Status**: pending
**Depends on**: 001, 002, 003, 004, 005, 006, 007, 008, 009, 010, 011, 012, 013, 014
**Retry count**: 0

## Description
Write the `README.md` for both `attribute` and `attribute-pgsql`, following the project's
Package README Standards. They must accurately reflect what was built across the plan.

## Context
- Standard: `.claude/code-standards.md` (Package README Standards) and `.claude/package-standard.md`.
- Reference existing slim READMEs (e.g. `packages/config/README.md`,
  `packages/config-pgsql/README.md`) for tone, length, and section layout.
- `attribute/README.md`: purpose (entity-agnostic attribute kernel), the type system + how to
  register/override a type, the `backing` concept, defining attributes + options, the reserved-code
  mechanism, and what is out of scope (values = Phase 2). Note PHP 8.5 / no-final / strict-types.
- `attribute-pgsql/README.md`: purpose (Postgres driver), the tables it creates, how it binds
  the repository, and how to run its integration tests.

## Requirements (Test Descriptions)
- [ ] `it documents the attribute package purpose and type registration in its README`
- [ ] `it documents the attribute-pgsql tables and binding in its README`

> Note: these "tests" are documentation-completeness checks — verify the README files exist and
> contain the required sections (a simple file-content assertion or manual review per project
> convention for README tasks).

## Acceptance Criteria
- Both READMEs follow the Package README Standard sections and length.
- Content matches the shipped API (no aspirational / unbuilt features).

## Implementation Notes
(Left blank - filled in by programmer during implementation)
