<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Config;

use Markommerce\Config\Attributes\Config;

class CatalogPaginationConfig
{
    #[Config(key: 'catalog/pagination.defaultPageSize')]
    public int $defaultPageSize = 24;

    /** @var list<int> */
    #[Config(key: 'catalog/pagination.allowedPageSizes')]
    public array $allowedPageSizes = [12, 24, 48, 96];

    #[Config(key: 'catalog/pagination.maxPageSize')]
    public int $maxPageSize = 96;

    #[Config(key: 'catalog/pagination.strategy')]
    public string $strategy = 'offset';

    #[Config(key: 'catalog/pagination.presentation')]
    public string $presentation = 'numbered';

    #[Config(key: 'catalog/pagination.countMode')]
    public string $countMode = 'exact';

    #[Config(key: 'catalog/pagination.maxPageDepth')]
    public int $maxPageDepth = 100;

    #[Config(key: 'catalog/pagination.defaultSort')]
    public string $defaultSort = 'position';

    /** @var list<string> */
    #[Config(key: 'catalog/pagination.allowedSorts')]
    public array $allowedSorts = ['position', 'name', 'sku', 'price'];

    #[Config(key: 'catalog/pagination.viewAllThreshold')]
    public int $viewAllThreshold = 0;

    #[Config(key: 'catalog/pagination.countCacheTtl')]
    public int $countCacheTtl = 0;
}
