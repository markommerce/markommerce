<?php

declare(strict_types=1);

use Markommerce\Attribute\Entity\AttributeDefinition;
use Markommerce\Attribute\Entity\AttributeOption;
use Markommerce\Attribute\Exceptions\AttributeDefinitionNotFoundException;
use Markommerce\Attribute\Exceptions\InvalidAttributeOptionException;
use Markommerce\Attribute\Exceptions\InvalidAttributeValueException;
use Markommerce\Attribute\Registry\AttributeTypeRegistry;
use Markommerce\Attribute\Type\DecimalType;
use Markommerce\Attribute\Type\MultiselectType;
use Markommerce\Attribute\Type\SelectType;
use Markommerce\Attribute\Type\TextType;
use Markommerce\Attribute\Validation\AttributeValueValidator;
use Markommerce\Catalog\Entity\Product;
use Markommerce\CatalogAttribute\Definition\ProductAttributeDefinitions;
use Markommerce\CatalogAttribute\Definition\StaticAttributeProvider;
use Markommerce\CatalogAttribute\Entity\ProductAttributeValues;
use Markommerce\CatalogAttribute\ProductAttributeAccessor;
use Markommerce\CatalogAttribute\Tests\Support\FakeAttributeDefinitionRepository;

function makeAccessor(
    FakeAttributeDefinitionRepository $repo,
): ProductAttributeAccessor {
    $registry = new AttributeTypeRegistry();
    $registry->register(new TextType());
    $registry->register(new DecimalType());
    $registry->register(new SelectType());
    $registry->register(new MultiselectType());

    $validator = new AttributeValueValidator($registry);
    $provider = new StaticAttributeProvider();
    $definitions = new ProductAttributeDefinitions($repo, $provider);

    return new ProductAttributeAccessor($definitions, $validator, $repo, $provider);
}

function makeCustomDefinition(
    FakeAttributeDefinitionRepository $repo,
    string $code = 'color',
    string $type = 'text',
    string $backing = 'Json',
    ?string $defaultValue = null,
): AttributeDefinition {
    $def = new AttributeDefinition();
    $def->code = $code;
    $def->entityType = 'product';
    $def->type = $type;
    $def->backing = $backing;
    $def->defaultValue = $defaultValue;
    $repo->save($def);

    return $def;
}

function makeSelectDefinitionWithOptions(
    FakeAttributeDefinitionRepository $repo,
    string $code,
    array $optionValues,
): AttributeDefinition {
    $def = new AttributeDefinition();
    $def->code = $code;
    $def->entityType = 'product';
    $def->type = 'select';
    $def->backing = 'Json';
    $repo->save($def);

    foreach ($optionValues as $value) {
        $option = new AttributeOption();
        $option->attributeId = $def->id;
        $option->value = $value;
        $option->label = $value;
        $repo->saveOption($option);
    }

    return $def;
}

it('sets and gets a Json-backed value on the product companion', function (): void {
    $repo = new FakeAttributeDefinitionRepository();
    makeCustomDefinition($repo, code: 'color', type: 'text', backing: 'Json');

    $accessor = makeAccessor($repo);
    $product = new Product();

    $accessor->set($product, 'color', 'red');
    $result = $accessor->get($product, 'color');

    expect($result)->toBe('red');

    $companion = $product->companion(ProductAttributeValues::class);
    expect($companion)->toBeInstanceOf(ProductAttributeValues::class);
    expect($companion->get('color'))->toBe('red');
});

it('sets a Column-backed value onto the native product property', function (): void {
    $repo = new FakeAttributeDefinitionRepository();
    $accessor = makeAccessor($repo);
    $product = new Product();

    $accessor->set($product, 'name', 'Widget Pro');

    expect($product->name)->toBe('Widget Pro');
});

it('gets a Column-backed value from the native product property', function (): void {
    $repo = new FakeAttributeDefinitionRepository();
    $accessor = makeAccessor($repo);
    $product = new Product();
    $product->sku = 'SKU-001';

    $result = $accessor->get($product, 'sku');

    expect($result)->toBe('SKU-001');
});

it('validates and casts the value via the attribute validator before storing', function (): void {
    $repo = new FakeAttributeDefinitionRepository();
    makeCustomDefinition($repo, code: 'weight', type: 'decimal', backing: 'Json');

    $accessor = makeAccessor($repo);
    $product = new Product();

    // Pass an integer — DecimalType casts it to string
    $accessor->set($product, 'weight', 10);
    $result = $accessor->get($product, 'weight');

    expect($result)->toBe('10');
});

it('rejects an invalid value by throwing from the validator', function (): void {
    $repo = new FakeAttributeDefinitionRepository();
    makeCustomDefinition($repo, code: 'weight', type: 'decimal', backing: 'Json');

    $accessor = makeAccessor($repo);
    $product = new Product();

    expect(fn () => $accessor->set($product, 'weight', 'not-a-number'))
        ->toThrow(InvalidAttributeValueException::class);
});

it('enforces select option membership using options loaded for the definition', function (): void {
    $repo = new FakeAttributeDefinitionRepository();
    makeSelectDefinitionWithOptions($repo, 'color', ['red', 'green', 'blue']);

    $accessor = makeAccessor($repo);
    $product = new Product();

    // valid value passes
    $accessor->set($product, 'color', 'red');
    expect($accessor->get($product, 'color'))->toBe('red');

    // invalid option throws
    expect(fn () => $accessor->set($product, 'color', 'yellow'))
        ->toThrow(InvalidAttributeOptionException::class);
});

it('throws AttributeDefinitionNotFoundException for an unknown code', function (): void {
    $repo = new FakeAttributeDefinitionRepository();
    $accessor = makeAccessor($repo);
    $product = new Product();

    expect(fn () => $accessor->set($product, 'nonexistent', 'value'))
        ->toThrow(AttributeDefinitionNotFoundException::class);

    expect(fn () => $accessor->get($product, 'nonexistent'))
        ->toThrow(AttributeDefinitionNotFoundException::class);

    expect(fn () => $accessor->clear($product, 'nonexistent'))
        ->toThrow(AttributeDefinitionNotFoundException::class);
});

it('falls back to the definition default when no value is stored', function (): void {
    $repo = new FakeAttributeDefinitionRepository();
    makeCustomDefinition($repo, code: 'tag', type: 'text', backing: 'Json', defaultValue: 'general');

    $accessor = makeAccessor($repo);
    $product = new Product();

    // Nothing stored yet — should return the cast default
    $result = $accessor->get($product, 'tag');

    expect($result)->toBe('general');
});

it('falls back to decimal default cast to string for a Column-backed attribute', function (): void {
    $repo = new FakeAttributeDefinitionRepository();
    $accessor = makeAccessor($repo);
    $product = new Product();
    // priceAmount is nullable string; nothing set = null, no default on static def
    $result = $accessor->get($product, 'priceAmount');

    expect($result)->toBeNull();
});

it('includes every static attribute in all even when no value is explicitly stored', function (): void {
    $repo = new FakeAttributeDefinitionRepository();
    $accessor = makeAccessor($repo);
    $product = new Product();
    $product->sku = 'ABC-123';
    $product->name = 'Test Product';

    $all = $accessor->all($product);

    expect($all)->toHaveKey('sku')
        ->and($all)->toHaveKey('name')
        ->and($all)->toHaveKey('priceAmount')
        ->and($all['sku'])->toBe('ABC-123')
        ->and($all['name'])->toBe('Test Product')
        ->and($all['priceAmount'])->toBeNull();
});

it('includes stored custom values alongside statics in all', function (): void {
    $repo = new FakeAttributeDefinitionRepository();
    makeCustomDefinition($repo, code: 'color', type: 'text', backing: 'Json');

    $accessor = makeAccessor($repo);
    $product = new Product();
    $product->sku = 'P-42';
    $product->name = 'Widget';

    $accessor->set($product, 'color', 'blue');

    $all = $accessor->all($product);

    expect($all)->toHaveKey('sku')
        ->and($all)->toHaveKey('name')
        ->and($all)->toHaveKey('priceAmount')
        ->and($all)->toHaveKey('color')
        ->and($all['sku'])->toBe('P-42')
        ->and($all['name'])->toBe('Widget')
        ->and($all['color'])->toBe('blue');
});
