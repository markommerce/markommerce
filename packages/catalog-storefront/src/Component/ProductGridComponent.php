<?php

declare(strict_types=1);

namespace Markommerce\CatalogStorefront\Component;

use Marko\Database\Exceptions\RepositoryException;
use Markommerce\Catalog\Entity\Category;
use Markommerce\Catalog\Services\CategoryAssignmentService;
use Markommerce\CatalogStorefront\Data\ProductGridData;
use Markommerce\Layout\ExtensionBag;
use Markommerce\MoneyIntl\MoneyFormatter;
use Markommerce\Pricing\Contracts\PriceResolverInterface;
use Markommerce\Pricing\Exceptions\PriceUnavailableException;
use Markommerce\Pricing\PriceContext;

class ProductGridComponent
{
    public function __construct(
        private CategoryAssignmentService $categoryAssignmentService,
        private PriceResolverInterface $priceResolver,
        private MoneyFormatter $moneyFormatter,
    ) {}

    /**
     * @throws RepositoryException
     */
    public function data(Category $category): ProductGridData
    {
        $id = $category->id;
        $products = $id !== null
            ? $this->categoryAssignmentService->productsInCategory($id)
            : [];

        $resolvedNames = [];
        $resolvedDescs = [];
        $formattedPrices = [];

        foreach ($products as $product) {
            if ($product->id === null) {
                continue;
            }

            $resolvedNames[$product->id] = $product->name;
            $resolvedDescs[$product->id] = $product->description;

            try {
                $money = $this->priceResolver->resolve(PriceContext::forProduct($product));
                $formattedPrices[$product->id] = $this->moneyFormatter->format($money);
            } catch (PriceUnavailableException) {
                $formattedPrices[$product->id] = null;
            }
        }

        return new ProductGridData(
            category: $category,
            products: $products,
            resolvedNames: $resolvedNames,
            resolvedDescs: $resolvedDescs,
            formattedPrices: $formattedPrices,
            extensions: new ExtensionBag(),
        );
    }
}
