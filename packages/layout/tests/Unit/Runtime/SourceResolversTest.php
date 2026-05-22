<?php

declare(strict_types=1);

use Marko\Core\Container\ContainerInterface;
use Markommerce\Layout\Exception\InvalidSourceTypeException;
use Marko\Routing\Http\Request;
use Markommerce\Layout\Runtime\ResolutionContext;
use Markommerce\Layout\Runtime\SourceResolver;
use Markommerce\Layout\Source\Source;

it('resolves a route source casting the value to its target type', function (): void {
    $context = new ResolutionContext(
        request: new Request(),
        routeParams: ['id' => '42'],
        contextMap: [],
        iterationItem: null,
        parentData: null,
        container: null,
        placementChain: 'storefront > product_show',
    );
    $resolver = new SourceResolver();

    $result = $resolver->resolve(Source::route('id', 'int'), $context);

    expect($result)->toBe(42);
});

it('resolves a query source falling back to the default when absent', function (): void {
    $context = new ResolutionContext(
        request: new Request(query: []),
        routeParams: [],
        contextMap: [],
        iterationItem: null,
        parentData: null,
        container: null,
        placementChain: 'storefront > catalog_list',
    );
    $resolver = new SourceResolver();

    $result = $resolver->resolve(Source::query('page', 1, 'int'), $context);

    expect($result)->toBe(1);
});

it('resolves a context source by token', function (): void {
    $product = new stdClass();
    $product->sku = 'ABC-123';

    $context = new ResolutionContext(
        request: new Request(),
        routeParams: [],
        contextMap: ['ProductContext' => $product],
        iterationItem: null,
        parentData: null,
        container: null,
        placementChain: 'storefront > product_show',
    );
    $resolver = new SourceResolver();

    $result = $resolver->resolve(Source::context('ProductContext'), $context);

    expect($result)->toBe($product);
});

it('resolves a context source walking a dot path into the value', function (): void {
    $price = new stdClass();
    $price->amount = 1999;

    $product = new stdClass();
    $product->price = $price;

    $context = new ResolutionContext(
        request: new Request(),
        routeParams: [],
        contextMap: ['ProductContext' => $product],
        iterationItem: null,
        parentData: null,
        container: null,
        placementChain: 'storefront > product_show',
    );
    $resolver = new SourceResolver();

    $result = $resolver->resolve(Source::context('ProductContext', 'price.amount'), $context);

    expect($result)->toBe(1999);
});

it('resolves an iterated source to the current iteration item', function (): void {
    $item = new stdClass();
    $item->name = 'Widget';

    $context = new ResolutionContext(
        request: new Request(),
        routeParams: [],
        contextMap: [],
        iterationItem: $item,
        parentData: null,
        container: null,
        placementChain: 'storefront > product_grid > product_card',
    );
    $resolver = new SourceResolver();

    $result = $resolver->resolve(Source::iterated('ProductToken'), $context);

    expect($result)->toBe($item);
});

it('resolves a parent-data source by reading a key from the parent data DTO', function (): void {
    $dataDto = new class {
        public string $sku = 'XYZ-999';
    };

    $context = new ResolutionContext(
        request: new Request(),
        routeParams: [],
        contextMap: [],
        iterationItem: null,
        parentData: $dataDto,
        container: null,
        placementChain: 'storefront > product_show > product_header',
    );
    $resolver = new SourceResolver();

    $result = $resolver->resolve(Source::parentData('sku', 'string'), $context);

    expect($result)->toBe('XYZ-999');
});

it('resolves a service source from the container', function (): void {
    $service = new stdClass();

    $container = new class ($service) implements ContainerInterface {
        public function __construct(private object $service) {}

        public function get(string $id): mixed
        {
            return $this->service;
        }

        public function has(string $id): bool
        {
            return true;
        }

        public function singleton(string $id): void {}

        public function instance(string $id, object $instance): void {}

        public function call(\Closure $callable): mixed
        {
            return null;
        }
    };

    $context = new ResolutionContext(
        request: new Request(),
        routeParams: [],
        contextMap: [],
        iterationItem: null,
        parentData: null,
        container: $container,
        placementChain: 'storefront > sidebar',
    );
    $resolver = new SourceResolver();

    $result = $resolver->resolve(Source::service('SomeService'), $context);

    expect($result)->toBe($service);
});

it('passes a literal prop value through unchanged', function (): void {
    $context = new ResolutionContext(
        request: new Request(),
        routeParams: [],
        contextMap: [],
        iterationItem: null,
        parentData: null,
        container: null,
        placementChain: 'storefront > header',
    );
    $resolver = new SourceResolver();

    expect($resolver->resolve('hello', $context))->toBe('hello');
    expect($resolver->resolve(42, $context))->toBe(42);
    expect($resolver->resolve(true, $context))->toBe(true);
    expect($resolver->resolve(['key' => 'value'], $context))->toBe(['key' => 'value']);
});

it('throws InvalidSourceTypeException when a route value cannot be cast', function (): void {
    $context = new ResolutionContext(
        request: new Request(),
        routeParams: ['id' => 'not-a-number'],
        contextMap: [],
        iterationItem: null,
        parentData: null,
        container: null,
        placementChain: 'storefront > product_show',
    );
    $resolver = new SourceResolver();

    expect(fn () => $resolver->resolve(Source::route('id', 'int'), $context))
        ->toThrow(InvalidSourceTypeException::class);
});

it('throws a loud error when a dot path segment does not exist', function (): void {
    $product = new stdClass();
    $product->sku = 'ABC-123';

    $context = new ResolutionContext(
        request: new Request(),
        routeParams: [],
        contextMap: ['ProductContext' => $product],
        iterationItem: null,
        parentData: null,
        container: null,
        placementChain: 'storefront > product_show',
    );
    $resolver = new SourceResolver();

    expect(fn () => $resolver->resolve(Source::context('ProductContext', 'nonexistent.path'), $context))
        ->toThrow(\RuntimeException::class, 'nonexistent');
});
