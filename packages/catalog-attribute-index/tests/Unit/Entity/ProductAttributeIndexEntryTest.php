<?php

declare(strict_types=1);

use Marko\Database\Entity\EntityMetadataFactory;
use Markommerce\CatalogAttributeIndex\Entity\ProductAttributeIndexEntry;

it(
    'maps ProductAttributeIndexEntry to the catalog_product_attribute_index table with typed columns',
    function (): void {
        $factory = new EntityMetadataFactory();
        $metadata = $factory->parse(ProductAttributeIndexEntry::class);
    
        expect($metadata->tableName)->toBe('catalog_product_attribute_index');
    
        $columnNames = array_map(fn ($col) => $col->name, $metadata->columns);
    
        expect($columnNames)->toContain('id')
            ->and($columnNames)->toContain('product_id')
            ->and($columnNames)->toContain('attribute_code')
            ->and($columnNames)->toContain('scope_signature')
            ->and($columnNames)->toContain('value_text')
            ->and($columnNames)->toContain('value_number')
            ->and($columnNames)->toContain('value_bool')
            ->and($columnNames)->toContain('value_kind');
    
        $columnsByName = [];
        foreach ($metadata->columns as $col) {
            $columnsByName[$col->name] = $col;
        }
    
        expect($columnsByName['id']->primaryKey)->toBeTrue()
            ->and($columnsByName['id']->autoIncrement)->toBeTrue()
            ->and($columnsByName['value_text']->nullable)->toBeTrue()
            ->and($columnsByName['value_number']->nullable)->toBeTrue()
            ->and($columnsByName['value_bool']->nullable)->toBeTrue();
    }
);

it('declares the layered-nav composite indexes via Index attributes', function (): void {
    $factory = new EntityMetadataFactory();
    $metadata = $factory->parse(ProductAttributeIndexEntry::class);

    $indexesByName = [];
    foreach ($metadata->indexes as $index) {
        $indexesByName[$index->name] = $index;
    }

    expect($indexesByName)->toHaveKey('idx_cai_scope_code_text')
        ->and($indexesByName)->toHaveKey('idx_cai_scope_code_number')
        ->and($indexesByName)->toHaveKey('idx_cai_product_id');

    expect($indexesByName['idx_cai_scope_code_text']->columns)
        ->toBe(['scope_signature', 'attribute_code', 'value_text'])
        ->and($indexesByName['idx_cai_scope_code_text']->unique)->toBeFalse();

    expect($indexesByName['idx_cai_scope_code_number']->columns)
        ->toBe(['scope_signature', 'attribute_code', 'value_number'])
        ->and($indexesByName['idx_cai_scope_code_number']->unique)->toBeFalse();

    expect($indexesByName['idx_cai_product_id']->columns)
        ->toBe(['product_id'])
        ->and($indexesByName['idx_cai_product_id']->unique)->toBeFalse();
});
