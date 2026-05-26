# Markommerce Features Register

This document tracks the gap between **what markommerce forces every merchant
to install today** and **what each merchant *should* need to install** based
on the complexity of their shop.

The framing: a corner-shop merchant selling in one country, in one language,
with a hundred products should pay nothing — in install size, boot time, or
mental overhead — for the machinery that powers a multi-market, multi-language,
multi-channel international retailer. Every extra capability must be a
package you opt into, not a transitive dependency forced on you.

> **Legend**
> - **Current** — what `composer require` pulls in today (direct + transitive markommerce deps; `marko/*` framework deps omitted).
> - **Desired** — the minimal set after the planned splits. Names prefixed with `🆕` do not exist yet.

---

## Package inventory (today)

| Package | Type | Direct markommerce deps |
|---|---|---|
| `markommerce/core` | library | — |
| `markommerce/scope` | interface | — |
| `markommerce/scope-pgsql` | driver | `scope` |
| `markommerce/config` | interface | `scope` |
| `markommerce/config-pgsql` | driver | `config` |
| `markommerce/layout` | interface | — |
| `markommerce/frontend` | integration | — |
| `markommerce/theme-blank` | theme | `layout`, `frontend` |
| `markommerce/catalog` | domain | `scope`, `layout`, `frontend`, `theme-blank` |
| `markommerce/frontend-demo` | demo | `frontend`, `layout`, `theme-blank`, `catalog` (indirect) |
| `markommerce/layout-demo` | demo | `layout`, `theme-blank` |
| `markommerce/theme-blank-demo` | demo | `frontend`, `theme-blank` |

---

## Naming convention

All package names follow **`{primary-thing}-{modifier}`**, primary thing first.

- **Concept / axis packages** carry a bare name: `markommerce/locale`, `markommerce/market`, `markommerce/channel`.
- **Driver / variant packages** name the abstraction first, the variant second: `markommerce/scope-pgsql`, `markommerce/config-pgsql`, `markommerce/theme-blank` (a variant of theme), `markommerce/theme-blank-demo` (a variant of `theme-blank`).
- **Bridge / extension packages** name the *domain being extended* first, the capability being added second: `markommerce/catalog-scope`, `markommerce/catalog-locale`, `markommerce/catalog-market`, `markommerce/catalog-storefront`, `markommerce/config-scope`, `markommerce/config-locale`, `markommerce/config-market`.

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
HTML storefront, a theme, merchant-editable settings.

| | Packages |
|---|---|
| **Current** | `catalog` ⇒ also forces `scope`, `scope-pgsql`*, `layout`, `frontend`, `theme-blank`; plus `config` + `config-pgsql` for settings (which also pull `scope`) |
| **Desired** | `catalog` + `🆕 catalog-storefront` + `config` + `config-pgsql` + `layout` + `frontend` + `theme-blank` |
| **Removes** | `scope`, `scope-pgsql`, and the per-market tree machinery currently bundled in `catalog` |

\* `scope-pgsql` isn't a direct require but is needed at runtime today because catalog's persisted fields go through scope.

### Tier 2 — Multi-language shop
*Tier 1 plus translated content. Still one country, one currency, one
checkout, one category tree — but product names, category names, and
merchant-editable settings all need per-locale overrides.*

**Adds:** scoping infrastructure; locale as an axis; auto-wiring bridges that
register locale-scoped fields against catalog and config without merchant config.

| | Packages added on top of Tier 1 |
|---|---|
| **Current** | (none — already pulled in via Tier 1 today) |
| **Desired (headless)** | `scope` + `scope-pgsql` + `catalog-scope` + `🆕 config-scope` + `locale` + `catalog-locale` + `🆕 config-locale` |
| **Desired (storefront)** | headless stack + `catalog-storefront-scope` (locale-aware storefront rendering via Preference) |

### Tier 3 — Multi-market international
*Tier 2 plus multiple markets: distinct category trees per market, different
products available in different channels, market-specific config. Languages
and markets vary independently — a merchant could also reach Tier 3 from
Tier 1 if they need multi-market but not multi-language.*

**Adds:** market as an axis; auto-wiring bridges that scope catalog and config
fields by market; per-market category trees with active-tree resolution.

| | Packages added on top of Tier 2 |
|---|---|
| **Current** | (none — multi-market trees already ship inside `catalog`) |
| **Desired** | `🆕 market` + `🆕 catalog-market` + `🆕 config-market` + `🆕 catalog-market-category-trees` |

---

## Side-by-side: what each merchant installs

| Capability | Tier 1 | Tier 2 | Tier 3 |
|---|---|---|---|
| Products & categories | ✅ | ✅ | ✅ |
| Single global category tree | ✅ | ✅ | ✅ |
| Storefront HTML | ✅ | ✅ | ✅ |
| Themed layout | ✅ | ✅ | ✅ |
| Merchant-editable settings | ✅ | ✅ | ✅ |
| Translated product/category fields | — | ✅ | ✅ |
| Locale-aware storefront rendering | — | ✅ (storefront) | ✅ |
| Per-locale settings | — | ✅ | ✅ |
| Per-market category trees | — | — | ✅ |
| Per-market product fields / per-channel visibility | — | — | ✅ |

**Package count, Desired state:**
- Tier 1: 7 packages
- Tier 2 headless: 14 packages (+ `scope`, `scope-pgsql`, `catalog-scope`, `🆕 config-scope`, `locale`, `catalog-locale`, `🆕 config-locale`)
- Tier 2 storefront: 15 packages (headless + `catalog-storefront-scope` for locale-aware storefront rendering)
- Tier 3: 18 packages (+ `🆕 market`, `🆕 catalog-market`, `🆕 config-market`, `🆕 catalog-market-category-trees`)

**Package count today:** every merchant installs essentially the Tier 3 set, whether they use it or not.

---

## Proposed new packages

### Domain extensions

| Package | Purpose | Requires |
|---|---|---|
| `markommerce/catalog-storefront` | HTTP controllers, route registration, Latte views, theme integration for the public shop | `catalog`, `layout`, `frontend` |
| `markommerce/catalog-scope` | Machinery: substitutes catalog entities with scope-aware decorators; axis-agnostic | `catalog`, `scope` |
| `🆕 markommerce/config-scope` | Machinery: adds per-scope override resolution on top of plain config | `config`, `scope` |
| `🆕 markommerce/catalog-market-category-trees` | Multiple category trees with per-market assignment and active-tree resolution | `catalog`, `market` (transitively `scope`) |

### Storefront extensions

| Package | Purpose | Requires |
|---|---|---|
| `markommerce/catalog-storefront-scope` | Swaps `ProductGridComponent` with a locale-aware `ScopedProductGridComponent` via Marko Preference; renders translated product names and descriptions for the active locale | `catalog-storefront`, `catalog-scope` |

### Axis concept packages

| Package | Purpose | Requires |
|---|---|---|
| `🆕 markommerce/locale` | Declares the `locale` scope axis; future home for i18n helpers and locale-flavoured resolvers | `scope` |
| `🆕 markommerce/market` | Declares the `market` scope axis; future home for market-aware utilities | `scope` |
| `🆕 markommerce/channel` | Declares the `channel` scope axis; introduced when first real consumer exists | `scope` |

### Auto-wiring bridges (axis × domain)

These are tiny packages (a few dozen lines each). They contain no business
logic — just `module.php` declarations that register field-to-axis mappings
with the `ScopedFieldRegistry`. Installing the bridge IS the configuration.

| Package | Contributes | Requires |
|---|---|---|
| `🆕 markommerce/catalog-locale` | `Product.name`, `Product.description`, `Category.name` scoped by `locale` | `catalog-scope`, `locale` |
| `🆕 markommerce/catalog-market` | `Product.price`, `Product.visibility` scoped by `market` | `catalog-scope`, `market` |
| `🆕 markommerce/config-locale` | Translatable settings get per-locale overrides | `config-scope`, `locale` |
| `🆕 markommerce/config-market` | Market-varying settings get per-market overrides | `config-scope`, `market` |

---

## How auto-wiring works

Two pieces glue the bridges to the entities they decorate:

1. **`catalog-scope` swaps entities via Marko Preference.** When installed, every `ProductRepository::find()` returns a `ScopableProduct` (decorator that extends `Product` and routes field reads/writes through `ScopedDataSerializer`). Catalog itself never imports anything from scope.

2. **`ScopedFieldRegistry` is the single source of truth for which fields are scoped by which axes.** Two contribution paths feed the same registry:
   - **Boot-time attribute scan** — kept as an ergonomic shortcut. Merchant-defined entities can still use `#[Scoped(axes: […])]` and those declarations land in the registry.
   - **`module.php` contributions from bridges** — each axis × domain bridge (`catalog-locale`, `catalog-market`, …) registers its mappings at boot.

`ScopableProduct` consults the registry per field. Install `catalog-locale` alone → `Product.name` is locale-scoped. Add `catalog-market` → `Product.price` becomes market-scoped, and any field both bridges register unions to compound scoping (`['locale', 'market']`).

The merchant never writes a `'name' => ['locale']` config line. The set of installed bridges *is* the policy.

---

## Refactor phases

The work is broken into 5 sequential phases. Each phase is its own plan
under `.claude/plans/`, executed independently, leaving `develop` shippable
between phases. Cutover style is **rip-and-replace** (markommerce is pre-1.0,
no external consumers to deprecate against).

**Status legend:** `pending` | `in_progress` | `completed`

| # | Phase | Status | Plan / branch | Outcome |
|---|---|---|---|---|
| **P1** | Refactor scope's metadata layer to be registry-driven | `completed` | `scope-metadata-registry` | `ScopedFieldRegistry` is authoritative. Attributes still work via a boot-time scan that feeds the registry. Serializer reads only the registry. Foundation for everything below. |
| **P2** | Decouple `catalog` from `scope`; create `catalog-scope`, `locale`, `catalog-locale` | `completed` | `catalog-scope-decouple` | `Product` and `Category` become plain entities (no `#[Scoped]`, no `HasScopes` trait, no `Markommerce\Scope\…` imports). Multi-language behaviour shifts to the new bridge stack. Tier 2 reachable through the new architecture. |
| **P3** | Extract `catalog-storefront` from `catalog`; create `catalog-storefront-scope` | `completed` | `catalog-storefront-extract` | Move controllers, route registration, Latte templates, and asset wiring out of `catalog`. Tier 1 reachable. Headless catalog consumers stop pulling layout/frontend/theme. `catalog-storefront-scope` adds Preference-based locale-aware grid rendering for storefront merchants. |
| **P4** | Create `market`, `catalog-market`; extract `catalog-market-category-trees` | `pending` | tbd | Tier 3 reachable. `CategoryTreeMarketAssignment`, the per-market resolver, and multi-tree CRUD move out of `catalog`. Catalog keeps a single default tree. |
| **P5** | Decouple `config` from `scope`; create `config-scope`, `config-locale`, `config-market` | `pending` | tbd | Mirrors P2 for config. `config` becomes a plain key-value store; per-scope overlays come from `config-scope` + bridges. |

### Decisions locked in for all phases

- **Cutover style:** rip-and-replace. No deprecation shims, no parallel implementations.
- **Bridge default overridability:** deferred. Bridges ship with fixed defaults; merchants who want to deviate don't install the bridge. Revisit only if a real use case appears.
- **`channel` axis:** out of scope for this refactor. Add when the first real consumer materialises.
- **Demo packages:** updated as part of whichever phase first breaks them (likely P2 or P3).

---

## Open questions

- **Is `theme-blank` the right default theme name?** Once we have `catalog-storefront`, the theme becomes truly swappable. Consider renaming to `theme-base` or providing a `theme-starter` template.
- **Do we need a meta-package per tier?** E.g. `markommerce/starter-shop` requiring exactly the Tier 1 set, `markommerce/multi-language-shop` requiring Tier 2. Could be a nice onboarding shortcut.
- **Should default bridge mappings be overridable?** A merchant might want `Product.description` *not* to be locale-scoped. The cleanest answer is to not install `catalog-locale` and write a custom one-line module; the second-cleanest is a subtractive merchant config layer. Decide before the first bridge ships.
