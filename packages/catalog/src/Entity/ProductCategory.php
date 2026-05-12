<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Entity;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Index;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;

/**
 * Pivot entity required by Marko's #[BelongsToMany] attribute to resolve
 * many-to-many relationships between products and categories.
 *
 * The surrogate $id column exists solely because Marko ORM requires a
 * single-column primary key on every entity. It carries no domain meaning —
 * the real uniqueness constraint is enforced by the composite index on
 * (product_id, category_id).
 */
#[Table('product_categories')]
#[Index(name: 'idx_product_categories_unique', columns: ['product_id', 'category_id'], unique: true)]
class ProductCategory extends Entity
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Column(references: 'products.id', onDelete: 'CASCADE')]
    public int $productId;

    #[Column(references: 'categories.id', onDelete: 'CASCADE')]
    public int $categoryId;
}
