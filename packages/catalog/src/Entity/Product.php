<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Entity;

use Marko\Database\Attributes\BelongsToMany;
use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;

#[Table('products')]
class Product extends Entity
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Column(length: 255, unique: true)]
    public string $sku;

    /**
     * TODO multi-store: scope this per store-view when the stores/config module lands.
     */
    #[Column(length: 255)]
    public string $name;

    /**
     * TODO multi-store: currency will become store-scoped; today resolved by ProductPriceService via CurrencyConfigInterface default.
     */
    #[Column(type: 'BIGINT')]
    public int $basePriceAmount;

    /** @var array<Category> */
    #[BelongsToMany(entityClass: Category::class, pivotClass: ProductCategory::class, foreignKey: 'productId', relatedKey: 'categoryId')]
    public array $categories = [];
}
