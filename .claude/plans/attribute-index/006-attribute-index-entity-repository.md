# Task 006: `ProductAttributeIndexEntry` EAV entity + index repository

**Status**: pending
**Depends on**: 001, 004
**Retry count**: 0

## Description
Create the EAV index entity that stores one already-resolved row per
`(product_id, attribute_code, scope_signature)` with typed value columns, plus a repository
(delete-by-product + batch insert + facet/filter read helpers) built on the core `IndexRepository`.

## Context
- Place in `packages/catalog-attribute-index/src/`.
- `ProductAttributeIndexEntry` `#[Table('catalog_product_attribute_index')]`, extends
  `Marko\Database\Entity\Entity`. Columns: `id` (PK, auto), `product_id` (int), `attribute_code`
  (string), `scope_signature` (string; `''` for the global/base row), `value_text` (text, nullable),
  `value_number` (`decimal(20,4)` or numeric, nullable), `value_bool` (bool, nullable), `value_kind`
  (string — the attribute type code, for read-casting). Declare indexes for layered nav via class-level
  `#[Marko\Database\Attributes\Index(name: ..., columns: [...])]` attributes (CONFIRMED supported and
  used in-repo — see `packages/catalog/src/Entity/CategoryTreeNode.php` and `ProductCategoryAssignment.php`
  for composite UNIQUE and non-unique composite index declarations; `#[Index]` round-trips through the
  schema emitter). Declare non-unique composite indexes:
  `(scope_signature, attribute_code, value_text)`, `(scope_signature, attribute_code, value_number)`,
  and `(product_id)` (for delete-by-product). No raw emitter/migration needed.
- Each row holds the ALREADY-RESOLVED value for its signature (resolved at index time) — no JSONB
  overrides, no COALESCE at read time.
- `ProductAttributeIndexRepository` (uses the core `IndexRepository` helper):
  - `replaceForProducts(array $productIds, list<ProductAttributeIndexEntry> $rows): void` —
    delete-by-`product_id` then batch insert (variable row count per product; multiselect → many rows).
  - `truncate(): void`.
  - read helpers used by the fallback reader (task 008) + future faceting:
    `findValues(int $productId, string $code, string $signature): list<ProductAttributeIndexEntry>` —
    returns ALL rows for the tuple (a multiselect resolves to multiple member rows; single-valued kinds
    return 0 or 1). The reader collapses single-valued kinds to one row and collects multiselect members.
    (A `findValue(...): ?ProductAttributeIndexEntry` single-row convenience may wrap it for non-multiselect.)
- Storing typed values: route by the attribute type — text/select/multiselect → `value_text`
  (multiselect = one row per member); int/decimal → `value_number` (decimal as precision-safe
  string/numeric); bool → `value_bool`; date → `value_text` (ISO). Always set `value_kind`. Document
  the mapping.

## Requirements (Test Descriptions)
- [ ] `it maps ProductAttributeIndexEntry to the catalog_product_attribute_index table with typed columns`
- [ ] `it replaces all index rows for a product on reindex (delete then insert)`
- [ ] `it stores a multiselect value as one row per member`
- [ ] `it routes a numeric value to value_number and a text value to value_text`
- [ ] `it declares the layered-nav composite indexes via Index attributes`
- [ ] `it finds all index rows for a product code and signature including multiselect members`

## Acceptance Criteria
- EAV entity with typed columns + the documented indexes; resolved-per-signature rows.
- Repository does delete-by-product + batch insert via the core helper; read helper works.

## Implementation Notes
(Left blank - filled in by programmer during implementation)
