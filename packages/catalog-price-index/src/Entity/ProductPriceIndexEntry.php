<?php

declare(strict_types=1);

namespace Markommerce\CatalogPriceIndex\Entity;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;
use Markommerce\Scope\Storage\HasScopes;
use Markommerce\Scope\Storage\HasScopesInterface;

#[Table('catalog_product_price_index')]
class ProductPriceIndexEntry extends Entity implements HasScopesInterface
{
    use HasScopes;

    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Column(name: 'product_id', unique: true)]
    public int $productId = 0;

    #[Column(type: 'decimal(20,4)', nullable: true)]
    public ?string $amount = null;

    #[Column(name: 'currency_code', length: 3)]
    public string $currencyCode = '';
}
