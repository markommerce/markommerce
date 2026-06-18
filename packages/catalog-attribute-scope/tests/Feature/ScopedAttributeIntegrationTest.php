<?php

declare(strict_types=1);

namespace Markommerce\CatalogAttributeScope\Tests\Feature;

use Marko\Database\Connection\ConnectionInterface;
use Markommerce\Attribute\Contracts\AttributeDefinitionRepositoryInterface;
use Markommerce\Attribute\Entity\AttributeDefinition;
use Markommerce\Attribute\Entity\AttributeOption;
use Markommerce\Attribute\Registry\AttributeEntityClassMap;
use Markommerce\Attribute\Registry\AttributeTypeRegistry;
use Markommerce\Attribute\Reserved\ReservedCodeProvider;
use Markommerce\Attribute\Services\AttributeDefinitionService;
use Markommerce\AttributeScope\Entity\AttributeOptionScopedLabels;
use Markommerce\AttributeScope\ScopedOptionLabelResolver;
use Markommerce\Catalog\Entity\Product;
use Markommerce\Catalog\Repositories\ProductRepository;
use Markommerce\CatalogAttribute\ProductAttributeAccessor;
use Markommerce\CatalogAttributeScope\ScopedProductAttributeAccessor;
use Markommerce\Scope\Context\ScopeContext;
use Markommerce\Scope\Signature\ScopeSignature;
use Markommerce\Testing\Database\TestConnection;
use Markommerce\Testing\IntegrationTestCase;
use Markommerce\Testing\Profile\StoreProfile;

// ─── Helpers ─────────────────────────────────────────────────────────────────

function scopedAttributeVendorDir(): string
{
    return dirname(__DIR__, 4) . '/vendor';
}

function makeScopedAttributeProfile(): StoreProfile
{
    return StoreProfile::of(
        scopedAttributeVendorDir(),
        'markommerce/catalog-attribute-scope',
        'markommerce/attribute-scope',
        'markommerce/locale',
        'marko/database-pgsql',
    )->withLocales('default', 'en', 'de');
}

function makeScopedAttributeDefinitionService(IntegrationTestCase $testCase): AttributeDefinitionService
{
    /** @var AttributeDefinitionRepositoryInterface $definitionRepository */
    $definitionRepository = $testCase->get(AttributeDefinitionRepositoryInterface::class);

    /** @var AttributeTypeRegistry $typeRegistry */
    $typeRegistry = $testCase->get(AttributeTypeRegistry::class);

    /** @var ReservedCodeProvider $reservedCodeProvider */
    $reservedCodeProvider = $testCase->get(ReservedCodeProvider::class);

    /** @var AttributeEntityClassMap $entityClassMap */
    $entityClassMap = $testCase->get(AttributeEntityClassMap::class);

    return new AttributeDefinitionService(
        attributeDefinitionRepository: $definitionRepository,
        attributeTypeRegistry: $typeRegistry,
        reservedCodeProvider: $reservedCodeProvider,
        entityClassMap: $entityClassMap,
    );
}

// ─── Tests ───────────────────────────────────────────────────────────────────

it('it merges the scoped_attribute_values column into the catalog_products table', function (): void {
    TestConnection::skipIfUnavailable();

    $testCase = new IntegrationTestCase(makeScopedAttributeProfile());
    $testCase->setUpIntegration();

    try {
        /** @var ConnectionInterface $connection */
        $connection = $testCase->get(ConnectionInterface::class);

        $rows = $connection->query(
            "SELECT column_name FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'catalog_products' AND column_name = 'scoped_attribute_values'",
        );

        $columnNames = array_column($rows, 'column_name');

        expect($columnNames)->toContain('scoped_attribute_values');
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');

it('it merges the scoped_labels column into the attribute_options table', function (): void {
    TestConnection::skipIfUnavailable();

    $testCase = new IntegrationTestCase(makeScopedAttributeProfile());
    $testCase->setUpIntegration();

    try {
        /** @var ConnectionInterface $connection */
        $connection = $testCase->get(ConnectionInterface::class);

        $rows = $connection->query(
            "SELECT column_name FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'attribute_options' AND column_name = 'scoped_labels'",
        );

        $columnNames = array_column($rows, 'column_name');

        expect($columnNames)->toContain('scoped_labels');
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');

it('it round-trips a scoped Json value and resolves the override under a matching scope', function (): void {
    TestConnection::skipIfUnavailable();

    $testCase = new IntegrationTestCase(makeScopedAttributeProfile());
    $testCase->setUpIntegration();

    try {
        $service = makeScopedAttributeDefinitionService($testCase);

        $colorDef = new AttributeDefinition();
        $colorDef->code = 'color';
        $colorDef->entityType = 'product';
        $colorDef->type = 'text';
        $colorDef->label = 'Color';
        $colorDef->scopable = true;
        $colorDef->config = ['axes' => ['locale']];

        $service->create($colorDef);

        /** @var ScopedProductAttributeAccessor $accessor */
        $accessor = $testCase->get(ScopedProductAttributeAccessor::class);

        /** @var ProductRepository $productRepository */
        $productRepository = $testCase->get(ProductRepository::class);

        $product = new Product();
        $product->sku = 'TEST-SCOPED-001';
        $product->name = 'Test Scoped Product';

        $signature = ScopeSignature::fromArray(['locale' => 'de']);
        $accessor->setScoped($product, 'color', 'rot', $signature);

        $productRepository->save($product);

        /** @var Product $found */
        $found = $productRepository->find($product->id);

        expect($found)->not->toBeNull();

        $resolvedDe = null;
        $testCase->store->inScope(null, 'de', function () use ($accessor, $found, &$resolvedDe): void {
            $resolvedDe = $accessor->resolve($found, 'color');
        });

        expect($resolvedDe)->toBe('rot');
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');

it('it resolves the global value when no scoped override matches the active scope', function (): void {
    TestConnection::skipIfUnavailable();

    $testCase = new IntegrationTestCase(makeScopedAttributeProfile());
    $testCase->setUpIntegration();

    try {
        $service = makeScopedAttributeDefinitionService($testCase);

        $colorDef = new AttributeDefinition();
        $colorDef->code = 'color';
        $colorDef->entityType = 'product';
        $colorDef->type = 'text';
        $colorDef->label = 'Color';
        $colorDef->scopable = true;
        $colorDef->config = ['axes' => ['locale']];

        $service->create($colorDef);

        /** @var ProductAttributeAccessor $globalAccessor */
        $globalAccessor = $testCase->get(ProductAttributeAccessor::class);

        /** @var ScopedProductAttributeAccessor $accessor */
        $accessor = $testCase->get(ScopedProductAttributeAccessor::class);

        /** @var ProductRepository $productRepository */
        $productRepository = $testCase->get(ProductRepository::class);

        $product = new Product();
        $product->sku = 'TEST-FALLBACK-001';
        $product->name = 'Test Fallback Product';

        // Set global value via the Phase-2 accessor
        $globalAccessor->set($product, 'color', 'red');

        // Set scoped override only for 'de'; 'en' has no override so it falls back to global
        $signature = ScopeSignature::fromArray(['locale' => 'de']);
        $accessor->setScoped($product, 'color', 'rot', $signature);

        $productRepository->save($product);

        /** @var Product $found */
        $found = $productRepository->find($product->id);

        expect($found)->not->toBeNull();

        $resolvedEn = null;
        $testCase->store->inScope(null, 'en', function () use ($accessor, $found, &$resolvedEn): void {
            $resolvedEn = $accessor->resolve($found, 'color');
        });

        expect($resolvedEn)->toBe('red');
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');

it('it resolves a scoped option label under a matching scope and the base label otherwise', function (): void {
    TestConnection::skipIfUnavailable();

    $testCase = new IntegrationTestCase(makeScopedAttributeProfile());
    $testCase->setUpIntegration();

    try {
        /** @var AttributeDefinitionRepositoryInterface $definitionRepository */
        $definitionRepository = $testCase->get(AttributeDefinitionRepositoryInterface::class);

        $service = makeScopedAttributeDefinitionService($testCase);

        // Create a 'select' attribute with locale-scoped labels
        $sizeDef = new AttributeDefinition();
        $sizeDef->code = 'size';
        $sizeDef->entityType = 'product';
        $sizeDef->type = 'select';
        $sizeDef->label = 'Size';
        $sizeDef->scopable = true;
        $sizeDef->config = ['axes' => ['locale']];

        $option = new AttributeOption();
        $option->value = 'small';
        $option->label = 'Small';

        $service->create($sizeDef, [$option]);

        // Fetch the option — the attribute-scope module links AttributeOptionScopedLabels
        // as an extender so the companion is automatically attached during hydration
        $fetchedOptions = $definitionRepository->optionsFor($sizeDef);
        expect($fetchedOptions)->toHaveCount(1);

        $fetchedOption = $fetchedOptions[0];

        /** @var AttributeOptionScopedLabels $labelsCompanion */
        $labelsCompanion = $fetchedOption->companion(AttributeOptionScopedLabels::class);
        expect($labelsCompanion)->not->toBeNull();

        // Set a scoped label for 'de' locale
        $labelsCompanion->setOverride('locale:de', 'label', 'Klein');
        $definitionRepository->saveOption($fetchedOption);

        // Re-fetch to verify persistence round-trip
        $refetchedOptions = $definitionRepository->optionsFor($sizeDef);
        $refetchedOption = $refetchedOptions[0];

        /** @var AttributeOptionScopedLabels $refetchedLabels */
        $refetchedLabels = $refetchedOption->companion(AttributeOptionScopedLabels::class);
        expect($refetchedLabels)->not->toBeNull();

        /** @var ScopedOptionLabelResolver $resolver */
        $resolver = $testCase->get(ScopedOptionLabelResolver::class);

        /** @var ScopeContext $scopeContext */
        $scopeContext = $testCase->get(ScopeContext::class);

        // Under 'de' — returns the scoped override
        $resolvedDe = null;
        $testCase->store->inScope(
            null,
            'de',
            function () use ($resolver, $refetchedOption, $refetchedLabels, $scopeContext, &$resolvedDe): void {
                $resolvedDe = $resolver->resolve($refetchedOption, $refetchedLabels, $scopeContext);
            },
        );

        // Under 'en' — no override, falls back to the base label
        $resolvedEn = null;
        $testCase->store->inScope(
            null,
            'en',
            function () use ($resolver, $refetchedOption, $refetchedLabels, $scopeContext, &$resolvedEn): void {
                $resolvedEn = $resolver->resolve($refetchedOption, $refetchedLabels, $scopeContext);
            },
        );

        expect($resolvedDe)->toBe('Klein');
        expect($resolvedEn)->toBe('Small');
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');
