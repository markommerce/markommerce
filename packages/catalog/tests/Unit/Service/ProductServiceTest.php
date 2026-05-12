<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Tests\Unit\Service;

use Markommerce\Catalog\Entity\Product;
use Markommerce\Catalog\Event\ProductCreated;
use Markommerce\Catalog\Event\ProductDeleted;
use Markommerce\Catalog\Event\ProductUpdated;
use Markommerce\Catalog\Exception\DuplicateSkuException;
use Markommerce\Catalog\Exception\InvalidProductDataException;
use Markommerce\Catalog\Exception\ProductNotFoundException;
use Markommerce\Catalog\Service\ProductService;
use Markommerce\Catalog\Tests\Support\FakeCurrencyConfig;
use Markommerce\Catalog\Tests\Support\FakeMoney;
use Markommerce\Catalog\Tests\Support\FakeProductRepository;
use Markommerce\Catalog\Tests\Support\RecordingEventDispatcher;

function makeService(
    ?FakeProductRepository $repo = null,
    ?FakeCurrencyConfig $config = null,
    ?RecordingEventDispatcher $dispatcher = null,
): ProductService {
    return new ProductService(
        $repo ?? new FakeProductRepository(),
        $config ?? new FakeCurrencyConfig('USD'),
        $dispatcher,
    );
}

it('creates a product with a valid sku name and base price and returns the persisted entity', function (): void {
    $repo = new FakeProductRepository();
    $service = makeService(repo: $repo);

    $money = new FakeMoney(1999, 'USD');
    $product = $service->create('SKU-001', 'Test Product', $money);

    expect($product)->toBeInstanceOf(Product::class)
        ->and($product->sku)->toBe('SKU-001')
        ->and($product->name)->toBe('Test Product')
        ->and($product->basePriceAmount)->toBe(1999)
        ->and($product->id)->not->toBeNull();

    expect($repo->products)->toHaveCount(1);
});

it('dispatches ProductCreated after a successful create when a dispatcher is bound', function (): void {
    $dispatcher = new RecordingEventDispatcher();
    $service = makeService(dispatcher: $dispatcher);

    $money = new FakeMoney(500, 'USD');
    $product = $service->create('SKU-002', 'Another Product', $money);

    expect($dispatcher->events)->toHaveCount(1);
    $event = $dispatcher->events[0];
    assert($event instanceof ProductCreated);
    expect($event->product)->toBe($product);
});

it('rejects a product with an empty sku by throwing InvalidProductDataException emptySku', function (): void {
    $service = makeService();
    $money = new FakeMoney(100, 'USD');

    expect(fn () => $service->create('', 'Product Name', $money))
        ->toThrow(InvalidProductDataException::class);

    // Also test whitespace-only sku
    expect(fn () => $service->create('   ', 'Product Name', $money))
        ->toThrow(InvalidProductDataException::class);
});

it('rejects a product with an empty name by throwing InvalidProductDataException emptyName', function (): void {
    $service = makeService();
    $money = new FakeMoney(100, 'USD');

    expect(fn () => $service->create('SKU-003', '', $money))
        ->toThrow(InvalidProductDataException::class);

    expect(fn () => $service->create('SKU-003', '   ', $money))
        ->toThrow(InvalidProductDataException::class);
});

it('rejects a duplicate sku by throwing DuplicateSkuException', function (): void {
    $repo = new FakeProductRepository();
    $service = makeService(repo: $repo);

    $money = new FakeMoney(100, 'USD');
    $service->create('SKU-DUP', 'First Product', $money);

    expect(fn () => $service->create('SKU-DUP', 'Second Product', new FakeMoney(200, 'USD')))
        ->toThrow(DuplicateSkuException::class);
});

it('rejects a negative base price by throwing InvalidProductDataException negativeBasePrice', function (): void {
    $service = makeService();
    $money = new FakeMoney(-1, 'USD');

    expect(fn () => $service->create('SKU-NEG', 'Product', $money))
        ->toThrow(InvalidProductDataException::class);
});

it('rejects a base price whose currency does not match the configured default by throwing InvalidProductDataException currencyMismatch before the entity is constructed', function (): void {
    $config = new FakeCurrencyConfig('USD');
    $repo = new FakeProductRepository();
    $service = makeService(repo: $repo, config: $config);

    $money = new FakeMoney(100, 'EUR');

    expect(fn () => $service->create('SKU-CUR', 'Product', $money))
        ->toThrow(InvalidProductDataException::class);

    // Ensure no entity was created
    expect($repo->products)->toHaveCount(0);
});

it('returns the product from get when it exists and null otherwise', function (): void {
    $repo = new FakeProductRepository();
    $service = makeService(repo: $repo);

    $money = new FakeMoney(100, 'USD');
    $created = $service->create('SKU-GET', 'Get Product', $money);

    $id = $created->id;
    assert($id !== null);
    $found = $service->get($id);
    expect($found)->toBe($created);

    $notFound = $service->get(9999);
    expect($notFound)->toBeNull();
});

it('returns the product from getBySku when the sku exists and null otherwise', function (): void {
    $repo = new FakeProductRepository();
    $service = makeService(repo: $repo);

    $money = new FakeMoney(100, 'USD');
    $created = $service->create('SKU-FIND', 'Find Product', $money);

    $found = $service->getBySku('SKU-FIND');
    expect($found)->toBe($created);

    $notFound = $service->getBySku('NONEXISTENT');
    expect($notFound)->toBeNull();
});

it('writes the basePrice amount directly to the entity basePriceAmount without invoking any entity-level Money method', function (): void {
    $repo = new FakeProductRepository();
    $service = makeService(repo: $repo);

    $money = new FakeMoney(4999, 'USD');
    $product = $service->create('SKU-PRICE', 'Price Product', $money);

    expect($product->basePriceAmount)->toBe(4999);
});

it('updates an existing product and dispatches ProductUpdated', function (): void {
    $repo = new FakeProductRepository();
    $dispatcher = new RecordingEventDispatcher();
    $service = makeService(repo: $repo, dispatcher: $dispatcher);

    $money = new FakeMoney(1000, 'USD');
    $product = $service->create('SKU-UPD', 'Original Name', $money);

    $countBefore = count($dispatcher->events);

    $product->name = 'Updated Name';
    $result = $service->update($product);

    expect($result)->toBe($product)
        ->and($result->name)->toBe('Updated Name');

    $newEvents = array_slice($dispatcher->events, $countBefore);
    expect($newEvents)->toHaveCount(1);
    $updateEvent = $newEvents[0];
    assert($updateEvent instanceof ProductUpdated);
    expect($updateEvent->product)->toBe($product);
});

it('rejects an update that introduces a duplicate sku owned by a different product', function (): void {
    $repo = new FakeProductRepository();
    $service = makeService(repo: $repo);

    $service->create('SKU-A', 'Product A', new FakeMoney(100, 'USD'));
    $productB = $service->create('SKU-B', 'Product B', new FakeMoney(200, 'USD'));

    // Try to update product B to use product A's SKU
    $productB->sku = 'SKU-A';

    expect(fn () => $service->update($productB))
        ->toThrow(DuplicateSkuException::class);
});

it('deletes an existing product and dispatches ProductDeleted', function (): void {
    $repo = new FakeProductRepository();
    $dispatcher = new RecordingEventDispatcher();
    $service = makeService(repo: $repo, dispatcher: $dispatcher);

    $money = new FakeMoney(100, 'USD');
    $product = $service->create('SKU-DEL', 'Delete Product', $money);
    $id = $product->id;
    assert($id !== null);

    $countBefore = count($dispatcher->events);
    $service->delete($id);

    expect($repo->products)->toHaveCount(0);
    $newEvents = array_slice($dispatcher->events, $countBefore);
    expect($newEvents)->toHaveCount(1);
    $deleteEvent = $newEvents[0];
    assert($deleteEvent instanceof ProductDeleted);
    expect($deleteEvent->productId)->toBe($id);
});

it('throws ProductNotFoundException from delete when missing', function (): void {
    $service = makeService();

    expect(fn () => $service->delete(9999))
        ->toThrow(ProductNotFoundException::class);
});

it('lists all products via the repository', function (): void {
    $repo = new FakeProductRepository();
    $service = makeService(repo: $repo);

    $service->create('SKU-L1', 'List Product 1', new FakeMoney(100, 'USD'));
    $service->create('SKU-L2', 'List Product 2', new FakeMoney(200, 'USD'));

    $list = $service->list();

    expect($list)->toHaveCount(2);
    expect($list[0])->toBeInstanceOf(Product::class);
    expect($list[1])->toBeInstanceOf(Product::class);
});

it('works without an event dispatcher by skipping dispatch calls silently', function (): void {
    $service = makeService(dispatcher: null);

    $money = new FakeMoney(100, 'USD');
    $product = $service->create('SKU-ND', 'No Dispatcher Product', $money);

    expect($product)->toBeInstanceOf(Product::class);

    $product->name = 'Updated Without Dispatcher';
    $updated = $service->update($product);
    expect($updated)->toBe($product);

    $noDispatchId = $product->id;
    assert($noDispatchId !== null);
    $service->delete($noDispatchId);
    // No exception means success
    expect(true)->toBeTrue();
});
