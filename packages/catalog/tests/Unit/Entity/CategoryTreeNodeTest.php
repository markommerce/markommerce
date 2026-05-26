<?php

declare(strict_types=1);

use Marko\Database\Attributes\Table;
use Markommerce\Catalog\Entity\CategoryTreeNode;

it('can be instantiated with default values', function (): void {
    $node = new CategoryTreeNode();

    expect($node)->toBeInstanceOf(CategoryTreeNode::class);
});

it('defaults parentNodeId to null marking a root placement', function (): void {
    $node = new CategoryTreeNode();

    expect($node->parentNodeId)->toBeNull();
});

it('defaults position to zero', function (): void {
    $node = new CategoryTreeNode();

    expect($node->position)->toBe(0);
});

it('exposes id, treeId, categoryId, parentNodeId, and position public properties', function (): void {
    $reflection = new ReflectionClass(CategoryTreeNode::class);

    expect($reflection->hasProperty('id'))->toBeTrue()
        ->and($reflection->hasProperty('treeId'))->toBeTrue()
        ->and($reflection->hasProperty('categoryId'))->toBeTrue()
        ->and($reflection->hasProperty('parentNodeId'))->toBeTrue()
        ->and($reflection->hasProperty('position'))->toBeTrue();
});

it('is mapped to the catalog_category_tree_nodes table', function (): void {
    $reflection = new ReflectionClass(CategoryTreeNode::class);
    $attributes = $reflection->getAttributes(Table::class);

    expect($attributes)->toHaveCount(1);

    $table = $attributes[0]->newInstance();

    expect($table->name)->toBe('catalog_category_tree_nodes');
});
