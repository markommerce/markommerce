<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Entity;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Index;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;

#[Table('catalog_product_category')]
#[Index(name: 'uniq_catalog_product_category', columns: ['product_id', 'category_id'], unique: true)]
class ProductCategoryAssignment extends Entity
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Column(name: 'product_id', references: 'catalog_products', onDelete: 'CASCADE')]
    public ?int $productId = null;

    #[Column(name: 'category_id', references: 'catalog_categories', onDelete: 'CASCADE')]
    public ?int $categoryId = null;

    #[Column(name: 'position', type: 'integer', nullable: false)]
    public int $position = 0;
}
