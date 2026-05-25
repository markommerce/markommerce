# Task 025: README for `markommerce/config-pgsql`

**Status**: completed
**Depends on**: 021, 022, 023
**Retry count**: 0

## Description
Write the slim README for the driver package. Cover: what it provides (PostgreSQL storage for `markommerce/config`), installation, configuration (DB connection, env vars), the migration command, and a pointer to the interface package for usage docs.

## Context
- Standards: `docs/DOCS-STANDARDS.md` for slim README format
- Reference: `packages/scope-pgsql/README.md`
- The README must NOT duplicate usage docs from `markommerce/config` — link to it instead
- Show how to install + run the migration + verify with a `config:set` round-trip

## Requirements (Test Descriptions)
- [x] `it has a README.md at the package root`
- [x] `it states the package provides the PgSQL storage driver for markommerce/config`
- [x] `it shows the installation steps including running the table migration`
- [x] `it documents required environment variables (DB connection + MARKOMMERCE_CONFIG_SECRET_KEY if secrets used)`
- [x] `it links to the interface package README and the docs site for usage`

## Acceptance Criteria
- README conforms to the slim format from `docs/DOCS-STANDARDS.md`
- Migration command example is accurate against task 022's emitter
- Internal links target real paths

## Implementation Notes
(Left blank — filled in by programmer)
