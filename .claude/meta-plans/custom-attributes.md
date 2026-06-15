# Meta-Plan: Custom Attributes

**Status:** Draft — phased roadmap. Each phase is fed to `hcf:plan-create` separately, in order.
**Owner:** Michał Biarda
**Last updated:** 2026-06-15

---

## 1. Goal

Give merchants the ability to define custom attributes at runtime (no code, no DDL),
attach values to products, scope those values by any axis (locale, market, …), and use
them for layered navigation, filtering, and search — **across all catalog sizes**.

The design is deliberately a "Magento alternative, but better": same CQRS shape Magento
uses (write model + derived read/index models) but built on Postgres strengths Magento's
MySQL core can't match, plus two things Magento structurally cannot retrofit:

1. **One entity-agnostic resolution kernel** reused across config + every entity (products
   first, categories/customers later). No per-entity EAV subsystems.
2. **Correctness-preserving indexing**: the index is a derived cache; a stale/missing index
   row falls back to live resolution from the source of truth, so staleness degrades to
   *slower*, never *wrong*. Magento's invalid-index state shows wrong data.

See the design discussion that produced this plan for the full Magento comparison and
rationale.

## 2. Architecture in one picture

```
                          ┌─ generated columns (hot global scalars) → plain SQL / BI
JSONB blob  ──────────────┼─ resolved index, partitioned by scope    → faceting / filtering
(source of truth, sparse) └─ search driver (PG FTS/vector ▸ ES swap) → search / layered nav
        │
        └─ resolve(entity, scope) ── builds the index AND serves as live fallback
```

- **Write model:** sparse JSONB blob per entity (single-row load, no EAV join explosion),
  patched server-side with `jsonb_set` + optimistic `version` (granular concurrent writes,
  no lost updates).
- **Resolution kernel:** reuses the existing signature machinery from `scope` /
  `config-scope` (`ScopeSignature`, `SignatureCandidateEnumerator`, `OverrideMatcher`,
  `ScopedFieldRegistry`). Deterministic `resolve(entity, scope)`.
- **Read surfaces (derived):** generated columns for hot global scalars; a resolved
  per-scope index partitioned by `scope_signature`; a pluggable search driver (pure
  Postgres by default — `tsvector`/`pg_trgm`/`pgvector` — swappable to ES/OpenSearch at
  hyperscale).
- **Index maintenance:** transactional (synchronous, no stale window) for small/mid;
  async via `LISTEN/NOTIFY` workers for bulk/hyperscale — both backed by the live-fallback
  guarantee. Invalidation via Observers, reusing the `catalog-price-index` pattern.

## 3. Cross-cutting principles (apply to every phase)

- Every PHP file `declare(strict_types=1);`; no `final`; `readonly class` where immutable;
  constructor injection only; explicit `@throws`; no traits except the established
  `HasScopes`-style ones already in the codebase; PHP 8.5 `array_*` idioms.
- Loud errors: every exception carries `message`, `context`, `suggestion`.
- Interface/driver split: contracts in the kernel package, Postgres specifics in `*-pgsql`.
- **Reuse, don't reinvent** the scope resolution engine. Attributes are a *consumer* of the
  `scope` kernel, exactly like `config-scope`.
- Postgres-specific magic lives only in `*-pgsql` drivers; contracts stay engine-neutral.
- Strategy is chosen by scale where relevant (e.g. generated-column promotion for small/mid,
  partitioned index for large) — never silently; log/observe what was chosen.
- TDD throughout; ≥80% coverage; unit suite stays DB-free, integration suite hits real
  Postgres (per `.claude/testing.md`).

## 4. Package map (created across phases)

Generic, entity-agnostic kernel + drivers, then product binding, then read surfaces.
Mirrors `scope` / `scope-pgsql` / `config-scope` / `catalog-scope` / `catalog-price-index`.

| Package | Phase | Role |
|---|---|---|
| `attribute` | 1 | Definitions, type registry, value contracts (interface) |
| `attribute-pgsql` | 1–2 | Definitions/options + value storage driver |
| `catalog-attribute` | 2 | Bind attributes to `Product` (companion, DI, services) |
| `attribute-scope` | 3 | Scope-aware resolution of attribute values + labels |
| `attribute-scope-pgsql` | 3 | Scoped override storage specifics (if needed) |
| `catalog-attribute-scope` | 3 | Wire product attributes to scopes |
| `attribute-index` | 4 | Resolved read-model + reindex + live-fallback contracts |
| `attribute-index-pgsql` | 4 | Resolved per-scope index, partitioning, NOTIFY, fallback |
| `catalog-attribute-index` | 4–5 | Product index wiring + Observers |
| `attribute-search` | 6 | Search/facet driver interface + Postgres default driver |
| `catalog-attribute-search` | 6 | Layered-navigation integration with catalog listing |

Final package names confirmed per phase during its `hcf:plan-create`.

---

## 5. Phases

> Each phase below is a self-contained `hcf:plan-create` input. Run them in order; later
> phases assume the earlier ones shipped. "Open decisions" must be resolved (with the user)
> before invoking `hcf:plan-create` for that phase.

### Phase 1 — Attribute definitions + pluggable type system (global only)

**Goal:** Merchants can define attributes at runtime; the type system is extensible.

**Scope**
- `attribute` package:
  - `AttributeDefinition` entity: `code` (validated, reserved-name guarded), `entityType`
    discriminator (`product` now; design for more), `type`, `label`, `required`,
    `defaultValue`, `validation`, a `backing` (`Column` | `Json` — see static attributes
    below), and flags `scopable` / `filterable` / `searchable` / `facetable` (consumed by
    later phases).
  - `AttributeTypeInterface` + registry: `text`, `int`, `decimal`, `bool`, `date`,
    `select`, `multiselect`, `entityRef`. Each type declares cast/validate, JSON
    (de)serialization, and (stub for now) its facet kind. Swappable via Preferences.
  - Options model for select/multiselect (`AttributeOption`: value, position; label
    translatable later) — separate rows, not buried in definition JSON.
  - Definition repository + service (CRUD), loud exceptions
    (`DuplicateAttributeCodeException`, `ReservedAttributeCodeException`,
    `UnknownAttributeTypeException`, …). Casting/validation modeled on config `ValueCaster`.
- `attribute-pgsql`: `attribute_definitions` + `attribute_options` tables + emitters.
- **Static attributes + backing abstraction** (decided — adopt Magento's good idea, improve
  the clumsy bits):
  - A definition's `backing` selects where its value lives: `Column` (a native entity
    column) or `Json` (the sparse blob from Phase 2). The value reader/writer and the
    resolution kernel dispatch on `backing` — giving **one uniform attribute API** over
    native columns and dynamic values (the payoff lands in Phases 4–6: faceting / filtering /
    search / admin iterate over attributes with a single code path).
  - **Reserved codes are derived from entity `#[Column]` metadata, not hand-maintained.** A
    boot step reads the bound entity's `EntityMetadata` and reserves every column name;
    a custom `code` that collides throws `ReservedAttributeCodeException` (loud, with
    suggestion). Single source of truth = the entity schema → no drift, no blocklist.
  - **Opt-in static definitions, not force-all.** All native column names are *reserved*,
    but column-backed static definitions are registered only for the columns worth exposing
    to attribute-driven features (e.g. `Product`: `sku`/`name`/`price_amount` filterable +
    facetable; `description` searchable-only; skip the rest). Any column can be promoted
    later by adding its definition — no migration, the column already exists.
  - This binding (which entity, which columns get static defs) lives in `catalog-attribute`
    (Phase 2), since it's product-specific; the `backing` concept + reserved-code derivation
    are kernel concerns here in Phase 1.

**Deliverable / acceptance:** Define/update/delete attributes and options; type registry
resolves and validates; new type pluggable via Preference in a test; a custom code that
shadows a native column name is rejected loudly. No values yet.

**Depends on:** nothing (greenfield kernel).

**Open decisions:** option labels storage shape. (Resolved: own entity for definitions;
reserved codes derived from entity metadata; static attributes via the `backing`
abstraction, opt-in subset.)

---

### Phase 2 — Value storage (JSONB blob, global scope) + product binding

**Goal:** Set/get custom attribute values on products (global scope), validated by type.

**Scope**
- `attribute` value contracts: `AttributeValueStorageInterface`, reader/writer that
  **dispatches on `backing`** — `Json`-backed values use **server-side `jsonb_set`** +
  optimistic `version` (no PHP read-modify-write); `Column`-backed (static) values read/write
  the native entity column. Validation against definitions on write (loud errors).
- `attribute-pgsql`: `Json` value storage driver (sparse JSONB blob).
- `catalog-attribute`: `ProductAttributes` companion (`#[Table(extends: Product::class)]`,
  blob column) for `Json`-backed values; **register the opt-in column-backed static
  definitions for `Product`** (`sku`/`name`/`price_amount`/…) and wire the `Column` backing
  to the native columns; DI/Preference wiring; attach attribute read/write to the product
  flow; ensure single-row product load stays cheap.

**Deliverable / acceptance:** Round-trip typed values on a product through both backings
(`color` via blob, `name`/`sku` via static column attributes) using one uniform API; invalid
value throws with suggestion; concurrent patches to different keys of one product both
persist (integration test).

**Depends on:** Phase 1.

**Open decisions:** companion vs. native column (recommend companion); bulk-write API shape
(defer heavy import to Phase 7).

---

### Phase 3 — Scoped attribute values (reuse signature kernel)

**Goal:** Attribute values **and** option labels resolve per scope with global fallback.

**Scope**
- `attribute-scope`: integrate `scope` signatures into attribute resolution. The kernel
  `resolve(entity, scope)` **dispatches by `backing`**: `Json`-backed values resolve from
  per-signature overrides in the blob (same shape as `HasScopes` `scopes` column);
  `Column`-backed (static) values resolve through the **existing `catalog-scope`** machinery
  (the `scopes` column + `PgSqlScopedFieldRenderer`) — so native columns and custom
  attributes share one scoped-resolution surface. Both paths use
  `SignatureCandidateEnumerator` + `OverrideMatcher`; register attribute axes via
  `ScopedFieldRegistry`; honor the `scopable` flag; scoped (translatable) option labels.
- `attribute-scope-pgsql`: override storage / query specifics if the blob shape needs driver
  support (e.g. `PgSqlScopedFieldRenderer`-style reads).
- `catalog-attribute-scope`: wire product attributes + labels to scopes.

**Deliverable / acceptance:** Same product resolves different values/labels under different
scope contexts with correct most-specific→global fallback (integration test mirroring
`config-scope` resolution tests).

**Depends on:** Phase 2 + existing `scope` kernel.

**Open decisions:** which axes are valid for attribute values vs. labels; partial vs. full
signature keys in the blob.

---

### Phase 4 — Resolved read model + correctness-preserving index

**Goal:** Fast scoped attribute reads; staleness never yields wrong data.

**Scope**
- `attribute-index` (interface): the deterministic `resolve(entity, scope)` used **both** to
  build index rows and as **live fallback**; index read/write contracts; reindex contracts
  (partial per-entity, full); freshness model.
- `attribute-index-pgsql`: resolved per-scope store — **start with the resolved-JSONB
  variant** (`(entity_id, scope_signature, resolved JSONB)` + GIN), native
  `PARTITION BY LIST (scope_signature)`; synchronous (transactional) and async
  (`LISTEN/NOTIFY` worker) index modes; live-resolution fallback path.
- `catalog-attribute-index`: Observers for invalidation (product save, value change,
  definition/option change) reusing the `catalog-price-index` pattern.

**Deliverable / acceptance:** Reads served from index hit fast path; deleting/staling an
index row still returns correct values via fallback and enqueues reindex; sync mode shows no
stale window (integration tests covering both modes).

**Depends on:** Phase 3.

**Open decisions:** resolved-JSONB vs. EAV-shaped index as the Phase-4 surface (recommend
resolved-JSONB now, EAV index in Phase 5 when counts are needed); which scope contexts are
materialized ("served scopes" registry); sync vs. async default.

---

### Phase 5 — Filtering & faceting (layered navigation)

**Goal:** Layered navigation over custom attributes, per scope, with counts and ranges.

**Scope**
- EAV-shaped facet index (`entity_id, scope_signature, attribute_code, value_code,
  value_label, value_number, value_bool`) **or** facet queries over the resolved store —
  decision from Phase 4. Term facets + range facets; facet-count queries; optional
  precomputed `(scope, category, attribute, value, count)` aggregate for hot entry points.
- Filter application (`EXISTS` per attribute; OR within an attribute, AND across).
- `catalog-attribute` listing integration: layered-navigation API (available facets + counts
  for the current filtered set + scope), filter DSL, integrate with `criteria` +
  catalog pagination/sorting.

**Deliverable / acceptance:** Given a category + scope + active filters, return remaining
products + per-attribute facet values with counts and range buckets, all indexed (no JSON
path gymnastics); translated labels per scope.

**Depends on:** Phase 4 + `criteria` + catalog listing.

**Open decisions:** live facet counts vs. precomputed aggregate (and its bounding);
keyset vs. offset interaction with facet filters.

---

### Phase 6 — Search drivers (tiered, pluggable) + adaptive projections

**Goal:** Full-text + faceted search over attributes with zero external infra by default;
swap to a search engine at hyperscale.

**Scope**
- `attribute-search` (interface): search/facet driver contract.
- Postgres default driver: `tsvector`+GIN full-text, `pg_trgm` fuzzy/typo, optional
  `pgvector` semantic; indexes searchable attribute values per scope.
- Adaptive projection: promote `filterable` attributes to **stored generated columns** +
  indexes via managed online migration (small/mid optimization); huge catalogs lean on the
  partitioned index instead — strategy chosen by scale, logged.
- ES/OpenSearch (or vector DB) swap driver behind the same interface (stub/contract +
  one reference driver if scoped in).
- `catalog-attribute-search`: layered-navigation + search integration for catalog listing.

**Deliverable / acceptance:** Full-text + faceted query over attributes on pure Postgres in
tests; documented swap path; projection promotion demonstrated on a flagged attribute.

**Depends on:** Phases 4–5.

**Open decisions:** scope of the ES reference driver (interface-only vs. full driver now);
pgvector inclusion now vs. later; projection-promotion migration safety on large tables.

---

### Phase 7 — API/admin surface, bulk import, hardening, docs (capstone)

**Goal:** End-to-end usable, performant, documented.

**Scope**
- Metadata-driven API/GraphQL/admin exposure of definitions (auto fields + filters from
  attribute metadata — a DX win over Magento's per-attribute resolvers).
- Bulk import path: batched server-side `jsonb_set` upserts; reindex coordination.
- Performance/partition tuning; index-mode guidance; migration tooling for adaptive schema.
- Docs pages (`docs/src/content/docs/packages/*`) + package READMEs via `doc-updater`.

**Deliverable / acceptance:** Define→value→scope→facet→search demonstrated end-to-end with
docs; bulk import benchmark; `doc-updater` clean.

**Depends on:** Phases 1–6.

**Open decisions:** API layer target (REST/GraphQL/admin priority); import format.

---

## 6. Sequencing

```
1 ─▶ 2 ─▶ 3 ─▶ 4 ─▶ 5 ─▶ 6 ─▶ 7
                      └─ 6 also depends on 2 (values) + 4 (index)
```

Phases 1–4 are the backbone (define → store → scope → index with fallback). 5 adds layered
navigation, 6 adds search + adaptive projections, 7 productionizes. Each phase ships
independently usable value and is plannable on its own with `hcf:plan-create`.

## 7. Global decisions

Resolved:
1. **Kernel package naming** — generic `attribute*` kernel + `catalog-attribute` binding
   (enables "attributes on other entities later").
2. **Definitions storage** — own entity (not `config`).
3. **Reserved codes / native columns** — adopt static attributes via a `backing`
   (`Column` | `Json`) abstraction for a uniform attribute API; reserved codes **derived from
   entity `#[Column]` metadata** (no hand-maintained blocklist); **opt-in** column-backed
   static definitions for the meaningful subset (not force-all). See Phases 1–3.
4. **Postgres-only** for the read-surface magic — accepted (already the stack); contracts
   stay engine-neutral, magic lives in `*-pgsql` drivers.

Still open (per-phase, listed under each phase): option-label storage shape; resolved-JSONB
vs. EAV index surface (Phase 4/5); live vs. precomputed facet counts (Phase 5); ES driver
scope (Phase 6).
