<?php

declare(strict_types=1);

use Markommerce\CatalogAttributeIndex\Entity\ProductAttributeIndexEntry;
use Markommerce\CatalogAttributeIndex\Repository\ProductAttributeIndexRepository;
use Markommerce\CatalogAttributeIndex\Tests\Support\FakeConnection;
use Markommerce\Indexer\Repository\IndexRepository;

it('replaces all index rows for a product on reindex (delete then insert)', function (): void {
    $connection = new FakeConnection();
    $indexRepo = new IndexRepository($connection);
    $repo = new ProductAttributeIndexRepository($indexRepo, $connection);

    $entry = new ProductAttributeIndexEntry();
    $entry->productId = 42;
    $entry->attributeCode = 'color';
    $entry->scopeSignature = '';
    $entry->valueText = 'red';
    $entry->valueKind = 'select';

    $repo->replaceForProducts([42], [$entry]);

    // First call: DELETE
    expect($connection->executed[0]['sql'])->toContain('DELETE')
        ->and($connection->executed[0]['sql'])->toContain('catalog_product_attribute_index')
        ->and($connection->executed[0]['bindings'])->toBe([42]);

    // Second call: INSERT
    expect($connection->executed[1]['sql'])->toContain('INSERT')
        ->and($connection->executed[1]['sql'])->toContain('catalog_product_attribute_index');
});

it('stores a multiselect value as one row per member', function (): void {
    $connection = new FakeConnection();
    $indexRepo = new IndexRepository($connection);
    $repo = new ProductAttributeIndexRepository($indexRepo, $connection);

    $red = new ProductAttributeIndexEntry();
    $red->productId = 7;
    $red->attributeCode = 'color';
    $red->scopeSignature = '';
    $red->valueText = 'red';
    $red->valueKind = 'multiselect';

    $blue = new ProductAttributeIndexEntry();
    $blue->productId = 7;
    $blue->attributeCode = 'color';
    $blue->scopeSignature = '';
    $blue->valueText = 'blue';
    $blue->valueKind = 'multiselect';

    $repo->replaceForProducts([7], [$red, $blue]);

    // DELETE first
    expect($connection->executed[0]['sql'])->toContain('DELETE')
        ->and($connection->executed[0]['bindings'])->toBe([7]);

    // INSERT with both members in a single statement
    $insertSql = $connection->executed[1]['sql'];
    expect($insertSql)->toContain('INSERT');

    $bindings = $connection->executed[1]['bindings'];
    // 7 columns × 2 rows = 14 binding values
    expect($bindings)->toHaveCount(14);

    // Both member values must appear in bindings
    expect(in_array('red', $bindings, true))->toBeTrue()
        ->and(in_array('blue', $bindings, true))->toBeTrue();
});

it('routes a numeric value to value_number and a text value to value_text', function (): void {
    $connection = new FakeConnection();
    $indexRepo = new IndexRepository($connection);
    $repo = new ProductAttributeIndexRepository($indexRepo, $connection);

    $numericEntry = new ProductAttributeIndexEntry();
    $numericEntry->productId = 1;
    $numericEntry->attributeCode = 'weight';
    $numericEntry->scopeSignature = '';
    $numericEntry->valueNumber = '12.5000';
    $numericEntry->valueKind = 'decimal';

    $textEntry = new ProductAttributeIndexEntry();
    $textEntry->productId = 2;
    $textEntry->attributeCode = 'description';
    $textEntry->scopeSignature = '';
    $textEntry->valueText = 'A fine product';
    $textEntry->valueKind = 'text';

    $repo->replaceForProducts([1, 2], [$numericEntry, $textEntry]);

    $bindings = $connection->executed[1]['bindings'];

    // Numeric entry: value_number = '12.5000', value_text = null
    // Columns order: product_id, attribute_code, scope_signature, value_text, value_number, value_bool, value_kind
    // Row 0: [1, 'weight', '', null, '12.5000', null, 'decimal']
    // Row 1: [2, 'description', '', 'A fine product', null, null, 'text']
    expect($bindings[0])->toBe(1)           // product_id
        ->and($bindings[3])->toBeNull()       // value_text for numeric
        ->and($bindings[4])->toBe('12.5000')  // value_number
        ->and($bindings[7])->toBe(2)          // product_id row 2
        ->and($bindings[10])->toBe('A fine product') // value_text
        ->and($bindings[11])->toBeNull();     // value_number for text
});

it('finds all index rows for a product code and signature including multiselect members', function (): void {
    $connection = new FakeConnection();
    $indexRepo = new IndexRepository($connection);
    $repo = new ProductAttributeIndexRepository($indexRepo, $connection);

    // Prime the query fake with two rows (as returned by the DB)
    $connection->queryResults = [[
        ['id' => '1', 'product_id' => '5', 'attribute_code' => 'color', 'scope_signature' => '', 'value_text' => 'red', 'value_number' => null, 'value_bool' => null, 'value_kind' => 'multiselect'],
        ['id' => '2', 'product_id' => '5', 'attribute_code' => 'color', 'scope_signature' => '', 'value_text' => 'blue', 'value_number' => null, 'value_bool' => null, 'value_kind' => 'multiselect'],
    ]];

    $results = $repo->findValues(productId: 5, code: 'color', signature: '');

    expect($results)->toHaveCount(2);

    expect($results[0])->toBeInstanceOf(ProductAttributeIndexEntry::class)
        ->and($results[0]->productId)->toBe(5)
        ->and($results[0]->attributeCode)->toBe('color')
        ->and($results[0]->valueText)->toBe('red')
        ->and($results[0]->valueKind)->toBe('multiselect');

    expect($results[1]->valueText)->toBe('blue');
});
