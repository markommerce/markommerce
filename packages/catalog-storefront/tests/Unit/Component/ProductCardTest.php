<?php

declare(strict_types=1);

use Latte\Engine;
use Marko\Config\ConfigRepository;
use Marko\Core\Module\ModuleManifest;
use Marko\Core\Module\ModuleRepository;
use Marko\View\Latte\LatteEngineFactory;
use Marko\View\Latte\ModuleLoader;
use Marko\View\ModuleTemplateResolver;
use Marko\View\ViewConfig;
use Markommerce\Catalog\Entity\Product;
use Markommerce\CatalogStorefront\Component\ProductCard;
use Markommerce\Money\Currency;
use Markommerce\Money\Money;
use Markommerce\MoneyIntl\MoneyFormatter;
use Markommerce\Pricing\Contracts\PriceResolverInterface;
use Markommerce\Pricing\Exceptions\PriceUnavailableException;
use Markommerce\Pricing\PriceContext;
use Markommerce\Scope\Axis\ScopeAxis;
use Markommerce\Scope\Context\ScopeContext;
use Markommerce\Scope\Exceptions\UnknownAxisException;
use Markommerce\Scope\Hierarchy\ScopeHierarchy;
use Markommerce\Scope\Registry\ScopeRegistryInterface;

// ─── Helpers ──────────────────────────────────────────────────────────────────

function productCardBuildLatte(): Engine
{
    $cacheDir = sys_get_temp_dir() . '/latte-catalog-card-test-' . bin2hex(random_bytes(8));
    mkdir($cacheDir, 0755, true);

    $catalogPath = dirname(__DIR__, 3);

    $moduleRepository = new ModuleRepository([
        new ModuleManifest(
            name: 'markommerce/catalog-storefront',
            version: '1.0.0',
            path: $catalogPath,
            source: 'vendor',
        ),
    ]);

    $config = new ConfigRepository([
        'view' => [
            'cache_directory' => $cacheDir,
            'extension' => '.latte',
            'auto_refresh' => true,
            'strict_types' => false,
        ],
    ]);

    $viewConfig = new ViewConfig($config);
    $templateResolver = new ModuleTemplateResolver($moduleRepository, $viewConfig);
    $engine = (new LatteEngineFactory($viewConfig))->create();
    $engine->setLoader(new ModuleLoader($templateResolver));

    return $engine;
}

function makeFakeScopeContext(?string $locale): ScopeContext
{
    $registry = new class () implements ScopeRegistryInterface
    {
        public function hasAxis(string $name): bool
        {
            return false;
        }

        public function getAxis(string $name): ScopeAxis
        {
            throw UnknownAxisException::forAxis($name);
        }

        /** @return list<string> */
        public function listAxes(): array
        {
            return [];
        }

        public function getHierarchy(string $axisName): ScopeHierarchy
        {
            throw UnknownAxisException::forAxis($axisName);
        }
    };

    return new class ($locale, $registry) extends ScopeContext
    {
        public function __construct(
            private readonly ?string $activeLocale,
            ScopeRegistryInterface $registry,
        ) {
            parent::__construct($registry);
        }

        public function get(string $axis): ?string
        {
            if ($axis === 'locale') {
                return $this->activeLocale;
            }

            return null;
        }
    };
}

function makeFakeMoneyFormatter(?string $locale = null): MoneyFormatter
{
    return new MoneyFormatter(makeFakeScopeContext($locale));
}

function makeUsdCurrency(): Currency
{
    return new Currency(code: 'USD', scale: 2, symbol: '$', name: 'US Dollar');
}

function makeFakePriceResolver(Money $money): PriceResolverInterface
{
    return new class ($money) implements PriceResolverInterface
    {
        public function __construct(private readonly Money $money) {}

        public function resolve(PriceContext $context): Money
        {
            return $this->money;
        }
    };
}

function makeNoPricePriceResolver(): PriceResolverInterface
{
    return new class () implements PriceResolverInterface
    {
        public function resolve(PriceContext $context): Money
        {
            throw PriceUnavailableException::forContext($context);
        }
    };
}

function productCardBuildComponent(
    PriceResolverInterface $priceResolver,
    MoneyFormatter $moneyFormatter,
): ProductCard {
    return new ProductCard($priceResolver, $moneyFormatter);
}

// ─── Tests ────────────────────────────────────────────────────────────────────

it('resolves and formats the product price into the product card data', function (): void {
    $product = new Product();
    $product->id = 1;
    $product->sku = 'SHOE-001';
    $product->name = 'Running Shoes';
    $product->priceAmount = '49.99';

    $money = Money::of('49.99', makeUsdCurrency());
    $priceResolver = makeFakePriceResolver($money);
    $moneyFormatter = makeFakeMoneyFormatter('en_US');

    $card = productCardBuildComponent($priceResolver, $moneyFormatter);
    $data = $card->data($product);

    expect($data->formattedPrice)->not->toBeNull()
        ->and($data->formattedPrice)->toContain('$')
        ->and($data->formattedPrice)->toContain('49.99');
});

it('leaves the formatted price null when the product has no price amount', function (): void {
    $product = new Product();
    $product->id = 2;
    $product->sku = 'GHOST-001';
    $product->name = 'Ghost Product';
    // intentionally no priceAmount set

    $priceResolver = makeNoPricePriceResolver();
    $moneyFormatter = makeFakeMoneyFormatter('en_US');

    $card = productCardBuildComponent($priceResolver, $moneyFormatter);
    $data = $card->data($product);

    expect($data->formattedPrice)->toBeNull();
});

it('renders the formatted price in the product card template', function (): void {
    $product = new Product();
    $product->id = 3;
    $product->sku = 'BOOT-001';
    $product->name = 'Hiking Boot';
    $product->priceAmount = '99.99';

    $money = Money::of('99.99', makeUsdCurrency());
    $priceResolver = makeFakePriceResolver($money);
    $moneyFormatter = makeFakeMoneyFormatter('en_US');

    $card = productCardBuildComponent($priceResolver, $moneyFormatter);
    $data = $card->data($product);

    $engine = productCardBuildLatte();
    $output = $engine->renderToString('catalog-storefront::components/product-card', [
        'product' => $data->product,
        'resolvedName' => $data->resolvedName,
        'resolvedDesc' => $data->resolvedDesc,
        'inStock' => $data->inStock,
        'formattedPrice' => $data->formattedPrice,
        'extensions' => $data->extensions,
    ]);

    expect($output)->toContain('mk-text')
        ->and($output)->toContain('catalog-product-card__price')
        ->and($output)->toContain('$')
        ->and($output)->toContain('99.99');
});

it('omits the price element from the card when there is no price', function (): void {
    $product = new Product();
    $product->id = 4;
    $product->sku = 'GHOST-002';
    $product->name = 'Unpriceable Widget';

    $priceResolver = makeNoPricePriceResolver();
    $moneyFormatter = makeFakeMoneyFormatter('en_US');

    $card = productCardBuildComponent($priceResolver, $moneyFormatter);
    $data = $card->data($product);

    $engine = productCardBuildLatte();
    $output = $engine->renderToString('catalog-storefront::components/product-card', [
        'product' => $data->product,
        'resolvedName' => $data->resolvedName,
        'resolvedDesc' => $data->resolvedDesc,
        'inStock' => $data->inStock,
        'formattedPrice' => $data->formattedPrice,
        'extensions' => $data->extensions,
    ]);

    expect($output)->not->toContain('catalog-product-card__price');
});
