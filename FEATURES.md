# Markommerce Features Register

This document tracks the gap between **what markommerce forces every merchant
to install today** and **what each merchant *should* need to install** based
on the complexity of their shop.

The framing: a corner-shop merchant selling in one country, in one language,
with a hundred products should pay nothing — in install size, boot time, or
mental overhead — for the machinery that powers a multi-market, multi-language,
multi-channel international retailer. Every extra capability must be a
package you opt into, not a transitive dependency forced on you.

**Status:** the original scope/config split (phases P1–P5 below) is **complete** —
`catalog` and `config` are now plain, scope-free domains, and the axis/bridge
packages that used to be proposals (`catalog-scope`, `locale`, `market`,
`catalog-storefront`, …) all ship today. Since then the framework also grew a
**commerce-primitives track** — money/currency/tax, a shared indexer, a criteria
(sort/paginate) engine, and custom product attributes with layered navigation —
catalogued in [Feature domains](#feature-domains) below.

> **Legend**
> - **Current** — what `composer require` pulls in today (direct + transitive markommerce deps; `marko/*` framework deps omitted).
> - **Desired** — the minimal set the architecture aims for. Names prefixed with `🆕` do not exist yet.

---

## Package inventory (today)

37 packages, grouped by role. "Direct markommerce deps" lists only `markommerce/*`
requires (not `marko/*` framework deps, not require-dev).

### Foundation & framework integration

| Package | Type | Direct markommerce deps |
|---|---|---|
| `markommerce/core` | library | — |
| `markommerce/scope` | library + pgsql impl | — |
| `markommerce/config` | library + pgsql impl | — |
| `markommerce/config-scope` | machinery + pgsql impl | `config`, `scope` |
| `markommerce/criteria` | engine | — |
| `markommerce/indexer` | kernel | `scope` |
| `markommerce/layout` | integration | — |
| `markommerce/frontend` | integration | — |
| `markommerce/theme-blank` | theme | `layout`, `frontend` |
| `markommerce/testing` | dev/test | — |

### Money, currency & tax

| Package | Type | Direct markommerce deps |
|---|---|---|
| `markommerce/money` | library | — |
| `markommerce/money-intl` | driver | `locale`, `money`, `scope` |
| `markommerce/currency` | domain | `config`, `money` |
| `markommerce/currency-market` | bridge | `config-scope`, `currency`, `market` |
| `markommerce/tax` | domain | `config` |
| `markommerce/tax-market` | bridge | `config-scope`, `market`, `tax` |

### Scope axes

| Package | Type | Direct markommerce deps |
|---|---|---|
| `markommerce/locale` | axis | `scope` |
| `markommerce/market` | axis | `scope` |

### Catalog & storefront

| Package | Type | Direct markommerce deps |
|---|---|---|
| `markommerce/catalog` | domain | `config`, `criteria`, `currency`, `money` |
| `markommerce/catalog-scope` | bridge | `catalog`, `scope` |
| `markommerce/catalog-locale` | bridge | `catalog-scope`, `locale` |
| `markommerce/catalog-market` | bridge | `catalog`, `catalog-scope`, `market` |
| `markommerce/catalog-storefront` | storefront | `catalog`, `catalog-price-index`, `currency`, `frontend`, `layout`, `money-intl`, `theme-blank` |
| `markommerce/catalog-storefront-scope` | bridge | `catalog-scope`, `catalog-storefront` |
| `markommerce/catalog-price-index` | index | `catalog`, `currency`, `indexer`, `money`, `scope` |
| `markommerce/catalog-price-index-market` | bridge | `catalog-market`, `catalog-price-index`, `market`, `scope` |

### Custom attributes & layered navigation

| Package | Type | Direct markommerce deps |
|---|---|---|
| `markommerce/attribute` | domain + pgsql impl | — |
| `markommerce/attribute-scope` | bridge | `attribute`, `scope` |
| `markommerce/catalog-attribute` | bridge | `attribute`, `catalog` |
| `markommerce/catalog-attribute-scope` | bridge | `attribute`, `catalog`, `catalog-attribute`, `scope` |
| `markommerce/catalog-attribute-index` | index | `attribute`, `catalog`, `catalog-attribute`, `catalog-attribute-scope`, `indexer`, `scope` |
| `markommerce/catalog-attribute-storefront` | storefront | `attribute`, `attribute-scope`, `catalog`, `catalog-attribute`, `catalog-attribute-index`, `catalog-attribute-scope`, `catalog-storefront`, `criteria`, `scope` |

### Config bridges

| Package | Type | Direct markommerce deps |
|---|---|---|
| `markommerce/config-locale` | bridge | `config-scope`, `locale` |
| `markommerce/config-market` | bridge | `config-scope`, `market` |

### Demos

| Package | Type | Direct markommerce deps |
|---|---|---|
| `markommerce/frontend-demo` | demo | `catalog`, `catalog-locale`, `catalog-scope`, `catalog-storefront`, `catalog-storefront-scope`, `frontend`, `layout`, `locale`, `theme-blank` |
| `markommerce/layout-demo` | demo | `layout`, `theme-blank` |
| `markommerce/theme-blank-demo` | demo | `frontend`, `theme-blank` |

---

## Naming convention

All package names follow **`{primary-thing}-{modifier}`**, primary thing first.

- **Concept / axis packages** carry a bare name: `markommerce/locale`, `markommerce/market`, `markommerce/channel`.
- **Driver / variant packages** name the abstraction first, the variant second: `markommerce/theme-blank` (a variant of theme), `markommerce/theme-blank-demo` (a variant of `theme-blank`). Markommerce assumes PostgreSQL as the database backend and ships no per-domain DB driver packages — each domain (`scope`, `config`, `config-scope`, `attribute`, …) bundles its Postgres implementation directly. The framework-level `marko/database-pgsql` driver still exists as the real DB dependency.
- **Bridge / extension packages** name the *domain being extended* first, the capability being added second: `markommerce/catalog-scope`, `markommerce/catalog-locale`, `markommerce/catalog-market`, `markommerce/catalog-storefront`, `markommerce/catalog-attribute`, `markommerce/config-scope`, `markommerce/config-locale`, `markommerce/config-market`, `markommerce/currency-market`, `markommerce/tax-market`.
- **Index packages** name the domain being indexed first: `markommerce/catalog-price-index`, `markommerce/catalog-attribute-index` (each may have its own `-market` axis bridge).

Why domain-first for bridges: it matches the merchant's mental model
("I have catalog. I want to add X."), groups every catalog-related package
next to `catalog` itself when listed alphabetically, and stays consistent
with the existing variant pattern.

Tie-breaker for genuinely peer-to-peer bridges: pick the package the merchant
would install first / talks to first. E.g. a future cart↔payment bridge
becomes `cart-payment` because cart triggers payment, not the other way around.

---

## Merchant tiers

Each tier adds **only** what the previous tier lacks. A merchant installs the
highest tier that describes their shop and gets exactly the right amount of
framework.

### Tier 1 — Corner shop
*One country. One language. One currency. A few hundred products. A single
global category tree. Settings are merchant-editable via a back office (no
deploy needed), but there are no per-locale or per-channel overrides.*

**Capabilities:** product & category CRUD, one global category tree, a public
HTML storefront, a theme, merchant-editable settings, a base currency + tax mode,
an effective-price index.

| | Packages |
|---|---|
| **Headless** | `catalog` (+ `config`, `criteria`, `currency`, `money`) — **scope-free today** ✅ |
| **Storefront** | headless + `catalog-storefront` + `catalog-price-index` + `frontend` + `layout` + `theme-blank` + `money-intl` |

**Achieved:** headless `catalog` no longer drags in `scope`, `layout`, `frontend`
or `theme-blank` (P2/P3). A catalog-only consumer is genuinely minimal.

**Known leak:** the **storefront** tier still pulls `scope` (and `locale`)
transitively — via `catalog-price-index` (`indexer` → `scope`) and `money-intl`
(`locale` + `scope`). So a single-locale storefront merchant currently installs
the scope machinery even without using it. See [Open questions](#open-questions).

### Tier 2 — Multi-language shop
*Tier 1 plus translated content. Still one country, one currency, one
checkout, one category tree — but product names, category names, and
merchant-editable settings all need per-locale overrides.*

**Adds:** scoping infrastructure; locale as an axis; auto-wiring bridges that
register locale-scoped fields against catalog and config without merchant config;
locale-aware money formatting and storefront rendering.

| | Packages added on top of Tier 1 | Total (cumulative) |
|---|---|---|
| **Headless** | `scope` (pgsql impl in-package) + `catalog-scope` + `config-scope` (pgsql impl in-package) + `locale` + `catalog-locale` + `config-locale` | **14 packages** (Tier 2 headless stack) |
| **Storefront** | headless stack + `catalog-storefront-scope` (locale-aware storefront rendering via Preference) | **15 packages** (Tier 2 storefront stack) |

All of these **ship today.**

### Tier 3 — Multi-market international
*Tier 2 plus multiple markets: distinct category trees per market, different
products available in different channels, market-specific config, per-market
currency and tax mode. Languages and markets vary independently.*

**Adds:** market as an axis; auto-wiring bridges that scope catalog, config,
currency, tax and the price index by market; per-market category trees with
active-tree resolution.

| | Packages added on top of Tier 2 | Tier 3 base total |
|---|---|---|
| **Shipped** | `market` + `catalog-market` + `config-market` + `currency-market` + `tax-market` + `catalog-price-index-market` | **18 packages** (Tier 2 headless + core Tier 3 additions) |

> Note: `catalog-market` ships as a placeholder bridge — it reserves the
> `ScopedFieldRegistry` hook for `Product.price` / `Product.visibility`, which are
> registered once those columns exist on `Product`.

---

## Side-by-side: what each merchant installs

| Capability | Tier 1 | Tier 2 | Tier 3 |
|---|---|---|---|
| Products & categories | ✅ | ✅ | ✅ |
| Single global category tree | ✅ | ✅ | ✅ |
| Storefront HTML + theme | ✅ | ✅ | ✅ |
| Merchant-editable settings | ✅ | ✅ | ✅ |
| Base currency + tax mode | ✅ | ✅ | ✅ |
| Effective-price index | ✅ | ✅ | ✅ |
| Custom product attributes | ✅ | ✅ | ✅ |
| Layered navigation (filter/facet) | ✅ | ✅ | ✅ |
| Translated product/category fields | — | ✅ | ✅ |
| Locale-aware storefront + money formatting | — | ✅ | ✅ |
| Per-locale settings & attribute labels | — | ✅ | ✅ |
| Per-market category trees | — | — | ✅ |
| Per-market price / currency / tax | — | — | ✅ |

Custom attributes & layered navigation are available from Tier 1 (they layer onto
`catalog` + `catalog-storefront`); their **scoped** behaviour (per-locale option
labels, per-market values) arrives with the matching axis bridges at Tiers 2/3.

---

## Feature domains

Beyond the scope/config tiering, the framework is organised into independent
commerce domains. Each is an interface/driver + bridge cluster you opt into.

### Money, currency & tax
- **`money`** — immutable `Money`/`Currency` value objects with exact BigDecimal arithmetic.
- **`money-intl`** — locale-aware formatting via `ext-intl` against the active locale scope.
- **`currency`** — globally configurable base currency (resolved into a `Money\Currency`); **`currency-market`** adds per-market overrides.
- **`tax`** — globally configurable tax-inclusive/exclusive mode (no rate computation yet); **`tax-market`** adds per-market overrides.

### Indexing
- **`indexer`** — shared indexer kernel: the abstract indexer contract, scope-pass runner, served-scopes provider, registry, and the unified `index:rebuild [name?]` command.
- **`catalog-price-index`** — materialises effective prices into a single bulk-upsert table (per-market overrides in a JSONB column); **`catalog-price-index-market`** registers the amount on the market axis.
- **`catalog-attribute-index`** — EAV attribute index (full per-signature materialization) powering filtering + faceting.

### Criteria
- **`criteria`** — sorting + pagination strategies (offset & keyset) and the page/position primitives the catalog listing builds on. (Filtering lives in `catalog`'s `ProductListFilter` registry; see below.)

### Custom attributes & layered navigation
A simpler-than-EAV custom-attribute system (JSON + static-column backing) with a
CQRS index for fast faceting:
- **`attribute`** — attribute definitions + options, value types/validation, reserved-code policy, the `backing` (Column|Json) abstraction (PostgreSQL driver bundled).
- **`catalog-attribute`** — binds the attribute kernel to `Product` (JSON companion + static column accessor).
- **`attribute-scope`** / **`catalog-attribute-scope`** — per-scope attribute values + translatable option labels.
- **`catalog-attribute-index`** — the disjunctive facet/filter index over the materialized read model.
- **`catalog-attribute-storefront`** — the storefront binding: an attribute `ProductListFilter` contributor, the layered-nav assembler, the facet sidebar + active-filter chips, and a layout extension that injects them into the category page. `catalog` stays attribute-agnostic via a generic `ProductListFilter` registry + `FilterSelection`.

---

## Packages that shipped (previously proposals)

The earlier revision of this doc listed many of these as `🆕` proposals. They now
exist; the auto-wiring design they were specified against is unchanged
(see [How auto-wiring works](#how-auto-wiring-works)).

| Package | Purpose |
|---|---|
| `catalog-storefront` | HTTP controllers, routes, Latte views, theme integration for the public shop |
| `catalog-storefront-scope` | Swaps `ProductGridComponent` with a locale-aware scoped component via Preference |
| `catalog-scope` / `config-scope` | Scope-aware decorators + per-scope override resolution (Postgres impl bundled in `config-scope`) |
| `catalog-market` / `config-market` | Market integration for catalog & config |
| `locale` / `market` | The `locale` and `market` scope-axis declarations |
| `catalog-locale` / `config-locale` | Auto-wiring bridges registering locale-scoped fields |

### Still proposed / not yet built

| Package | Status | Notes |
|---|---|---|
| `🆕 markommerce/channel` | not built | The `channel` scope axis — add when the first real consumer exists. |
| `🆕 markommerce/catalog-market` field mappings | partial | Bridge ships as a placeholder; `Product.price`/`Product.visibility` registrations deferred until those columns exist. |
| `🆕` per-tier meta-packages | idea | e.g. `starter-shop` (Tier 1), `multi-language-shop` (Tier 2) — onboarding shortcuts (see Open questions). |

---

## How auto-wiring works

Two pieces glue the bridges to the entities they decorate:

1. **`catalog-scope` swaps entities via Marko Preference.** When installed, every `ProductRepository::find()` returns a `ScopableProduct` (decorator that extends `Product` and routes field reads/writes through `ScopedDataSerializer`). Catalog itself never imports anything from scope.

2. **`ScopedFieldRegistry` is the single source of truth for which fields are scoped by which axes.** Two contribution paths feed the same registry:
   - **Boot-time attribute scan** — kept as an ergonomic shortcut. Merchant-defined entities can still use `#[Scoped(axes: […])]` and those declarations land in the registry.
   - **`module.php` contributions from bridges** — each axis × domain bridge (`catalog-locale`, `catalog-market`, …) registers its mappings at boot.

`ScopableProduct` consults the registry per field. Install `catalog-locale` alone → `Product.name` is locale-scoped. Add `catalog-market` → market-scoped fields union to compound scoping (`['locale', 'market']`).

The merchant never writes a `'name' => ['locale']` config line. The set of installed bridges *is* the policy.

The same pattern generalises beyond catalog: `config-scope` + `config-locale`/`config-market`
scope settings; `currency-market`/`tax-market` register their config keys on the
market axis; `attribute-scope`/`catalog-attribute-scope` add per-scope attribute
values and option labels.

---

## Refactor phases

The original scope/config split was broken into 5 sequential phases, each its own
plan under `.claude/plans/`, leaving `develop` shippable between phases. Cutover
style was **rip-and-replace** (markommerce is pre-1.0).

**Status legend:** `pending` | `in_progress` | `completed`

| # | Phase | Status | Plan / branch | Outcome |
|---|---|---|---|---|
| **P1** | Refactor scope's metadata layer to be registry-driven | `completed` | `scope-metadata-registry` | `ScopedFieldRegistry` is authoritative. Attributes still work via a boot-time scan that feeds the registry. Serializer reads only the registry. |
| **P2** | Decouple `catalog` from `scope`; create `catalog-scope`, `locale`, `catalog-locale` | `completed` | `catalog-scope-decouple` | `Product`/`Category` became plain entities. Multi-language behaviour shifted to the bridge stack. |
| **P3** | Extract `catalog-storefront` from `catalog`; create `catalog-storefront-scope` | `completed` | `catalog-storefront-extract` | Controllers/routes/templates/assets moved out of `catalog`. Headless catalog stopped pulling layout/frontend/theme. |
| **P4** | Create `market`, `catalog-market`; extract per-market category trees | `completed` | `catalog-market-extract` | Tier 3 reachable. Catalog keeps a single default tree. `catalog-market` ships as a no-op placeholder pending price/visibility columns. |
| **P5** | Decouple `config` from `scope`; create `config-scope`, `config-locale`, `config-market` | `completed` | `config-scope-decouple` | `config` became a plain key-value store; per-scope overlays come from `config-scope` + bridges. The `config` Postgres driver no longer emits a separate `overrides` JSONB column. |

### Commerce-primitives track (after the split)

Landed independently of the P1–P5 split, on the `custom-attributes` meta-plan and
related work:

| Area | Packages | Notes |
|---|---|---|
| Money/pricing | `money`, `money-intl`, `currency`(+`-market`), `tax`(+`-market`) | BigDecimal Money; configurable base currency + tax mode; effective-price pipeline. |
| Criteria | `criteria` | Sort + offset/keyset pagination engine for the catalog listing. |
| Indexing | `indexer`, `catalog-price-index`(+`-market`), `catalog-attribute-index` | Shared indexer kernel + unified `index:rebuild`; price + attribute read models. |
| Custom attributes | `attribute`(+`-scope`), `catalog-attribute`(+`-scope`,`-index`,`-storefront`) | Definitions/values/scoping + layered navigation (filter + disjunctive faceting) with a two-column storefront UI. Postgres impl bundled in `attribute`. |

### Decisions locked in

- **Cutover style:** rip-and-replace. No deprecation shims, no parallel implementations.
- **Bridge default overridability:** deferred. Bridges ship with fixed defaults; merchants who want to deviate don't install the bridge.
- **`channel` axis:** out of scope until the first real consumer materialises.

---

## Open questions

- **The storefront tier still pulls `scope`/`locale` transitively** (via `catalog-price-index` → `indexer` → `scope`, and `money-intl` → `locale`/`scope`). Should the price index and money formatting have scope-free defaults so a single-locale storefront stays minimal, or is a storefront always assumed to want scope? This is the main remaining gap against the Tier-1 ideal.
- **Is `theme-blank` the right default theme name?** With `catalog-storefront` shipped, the theme is swappable; consider `theme-base` or a `theme-starter` template.
- **Do we need a meta-package per tier?** E.g. `starter-shop` (Tier 1), `multi-language-shop` (Tier 2) as onboarding shortcuts.
- **Should default bridge mappings be overridable?** Cleanest answer remains "don't install the bridge; write a one-line module." Revisit only with a concrete use case.
- **`catalog-market` field registrations** are deferred until `Product.price`/`Product.visibility` columns exist — track alongside the pricing work.
