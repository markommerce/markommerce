<?php

declare(strict_types=1);

use Markommerce\CatalogAttributeIndex\Query\AttributeExistsClause;

it(
    'builds an EXISTS clause correlated to the given outer product column with a distinct inner alias',
    function (): void {
        $builder = new AttributeExistsClause();
    
        $result = $builder->build(
            outerColumn: 'i.product_id',
            attributeCode: 'color',
            values: ['red', 'blue'],
            signature: 'locale:en',
        );
    
        expect($result)->toBeArray()
            ->and($result)->toHaveKey('sql')
            ->and($result)->toHaveKey('bindings');
    
        // The inner alias must differ from the outer alias 'i'
    expect($result['sql'])->toContain('aei.product_id = i.product_id')
            ->and($result['sql'])->toContain('aei.attribute_code = ?')
            ->and($result['sql'])->toContain('aei.scope_signature = ?')
            ->and($result['sql'])->toContain('aei.value_text IN (?, ?)')
            ->and($result['sql'])->toStartWith('EXISTS (SELECT 1 FROM catalog_product_attribute_index aei WHERE');
    
        // Bindings: attribute_code, scope_signature, then values
    expect($result['bindings'])->toBe(['color', 'locale:en', 'red', 'blue']);
    }
);

it('accepts a custom inner alias to avoid self-join collisions', function (): void {
    $builder = new AttributeExistsClause();

    $result = $builder->build(
        outerColumn: 'catalog_products.id',
        attributeCode: 'size',
        values: ['M'],
        signature: 'locale:de',
        existsAlias: 'aei2',
    );

    expect($result['sql'])->toContain('aei2.product_id = catalog_products.id')
        ->and($result['sql'])->toContain('FROM catalog_product_attribute_index aei2 WHERE');

    expect($result['bindings'])->toBe(['size', 'locale:de', 'M']);
});

it('rejects an invalid outer column identifier', function (): void {
    $builder = new AttributeExistsClause();

    expect(fn () => $builder->build(
        outerColumn: 'invalid',
        attributeCode: 'color',
        values: ['red'],
        signature: '',
    ))->toThrow(InvalidArgumentException::class);
});
