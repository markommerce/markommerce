<?php

declare(strict_types=1);

namespace Markommerce\CatalogAttributeIndex\Entity;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Index;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;

/**
 * One already-resolved EAV row per (product_id, attribute_code, scope_signature).
 *
 * Value routing:
 *  - text / select / multiselect  → value_text  (multiselect = one row per member)
 *  - int / decimal                → value_number (precision-safe string/numeric)
 *  - bool                        → value_bool
 *  - date                        → value_text  (ISO 8601)
 *
 * value_kind always holds the attribute type code (e.g. 'text', 'select', 'bool').
 */
#[Table('catalog_product_attribute_index')]
#[Index(name: 'idx_cai_scope_code_text', columns: ['scope_signature', 'attribute_code', 'value_text'])]
#[Index(name: 'idx_cai_scope_code_number', columns: ['scope_signature', 'attribute_code', 'value_number'])]
#[Index(name: 'idx_cai_product_id', columns: ['product_id'])]
class ProductAttributeIndexEntry extends Entity
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Column(name: 'product_id')]
    public ?int $productId = null;

    #[Column(name: 'attribute_code')]
    public ?string $attributeCode = null;

    /** Empty string '' for the global/base scope row. */
    #[Column(name: 'scope_signature')]
    public ?string $scopeSignature = null;

    #[Column(name: 'value_text', nullable: true)]
    public ?string $valueText = null;

    #[Column(name: 'value_number', type: 'decimal', nullable: true)]
    public ?string $valueNumber = null;

    #[Column(name: 'value_bool', nullable: true)]
    public ?bool $valueBool = null;

    #[Column(name: 'value_kind')]
    public ?string $valueKind = null;
}
