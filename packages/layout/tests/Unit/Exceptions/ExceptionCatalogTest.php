<?php

declare(strict_types=1);

use Marko\Core\Exceptions\MarkoException;
use Markommerce\Layout\Exceptions\DanglingAnchorException;
use Markommerce\Layout\Exceptions\DuplicateNameException;
use Markommerce\Layout\Exceptions\ExtensionConflictException;
use Markommerce\Layout\Exceptions\InvalidSourceTypeException;
use Markommerce\Layout\Exceptions\LayoutException;
use Markommerce\Layout\Exceptions\MissingDataKeyException;
use Markommerce\Layout\Exceptions\MissingPropException;
use Markommerce\Layout\Exceptions\RepeatTypeMismatchException;
use Markommerce\Layout\Exceptions\TypeMismatchException;
use Markommerce\Layout\Exceptions\UnknownContextException;
use Markommerce\Layout\Exceptions\UnknownIterationException;

it('provides a LayoutException base extending MarkoException', function (): void {
    $exception = new LayoutException('test message');

    expect($exception)->toBeInstanceOf(MarkoException::class);
});

it('builds UnknownContextException naming the missing context token and layout', function (): void {
    $exception = UnknownContextException::forContext('my_context', 'storefront_layout');

    expect($exception)->toBeInstanceOf(LayoutException::class)
        ->and($exception->getMessage())->toContain('my_context')
        ->and($exception->getMessage())->toContain('storefront_layout');
});

it('builds UnknownIterationException naming the iteration token and the placement', function (): void {
    $exception = UnknownIterationException::forIteration(
        'item_loop',
        'storefront → category_show → content > product_grid'
    );

    expect($exception)->toBeInstanceOf(LayoutException::class)
        ->and($exception->getMessage())->toContain('item_loop')
        ->and($exception->getMessage())->toContain('storefront → category_show → content > product_grid');
});

it('builds TypeMismatchException naming expected type, actual type and the prop', function (): void {
    $exception = TypeMismatchException::forProp('title', 'string', 'int');

    expect($exception)->toBeInstanceOf(LayoutException::class)
        ->and($exception->getMessage())->toContain('title')
        ->and($exception->getMessage())->toContain('string')
        ->and($exception->getMessage())->toContain('int');
});

it('builds RepeatTypeMismatchException naming the yields type and the actual item type', function (): void {
    $exception = RepeatTypeMismatchException::forItem('ProductInterface', 'CategoryInterface');

    expect($exception)->toBeInstanceOf(LayoutException::class)
        ->and($exception->getMessage())->toContain('ProductInterface')
        ->and($exception->getMessage())->toContain('CategoryInterface');
});

it('builds DanglingAnchorException naming the missing anchor and the extension file', function (): void {
    $exception = DanglingAnchorException::forAnchor('content_top', '/path/to/extension.php');

    expect($exception)->toBeInstanceOf(LayoutException::class)
        ->and($exception->getMessage())->toContain('content_top')
        ->and($exception->getMessage())->toContain('/path/to/extension.php');
});

it('builds DuplicateNameException naming the duplicated placement name', function (): void {
    $exception = DuplicateNameException::forName('product_grid');

    expect($exception)->toBeInstanceOf(LayoutException::class)
        ->and($exception->getMessage())->toContain('product_grid');
});

it('builds MissingDataKeyException naming the data key and the component', function (): void {
    $exception = MissingDataKeyException::forKey('products', 'ProductGridComponent');

    expect($exception)->toBeInstanceOf(LayoutException::class)
        ->and($exception->getMessage())->toContain('products')
        ->and($exception->getMessage())->toContain('ProductGridComponent');
});

it('builds ExtensionConflictException naming the conflicting operations and priority', function (): void {
    $exception = ExtensionConflictException::forConflict('insertBefore', 'insertAfter', 10);

    expect($exception)->toBeInstanceOf(LayoutException::class)
        ->and($exception->getMessage())->toContain('insertBefore')
        ->and($exception->getMessage())->toContain('insertAfter')
        ->and($exception->getMessage())->toContain('10');
});

it('builds MissingPropException naming the required prop and the component', function (): void {
    $exception = MissingPropException::forProp('imageUrl', 'ProductCardComponent');

    expect($exception)->toBeInstanceOf(LayoutException::class)
        ->and($exception->getMessage())->toContain('imageUrl')
        ->and($exception->getMessage())->toContain('ProductCardComponent');
});

it('builds InvalidSourceTypeException naming the source, the value and the target type', function (): void {
    $exception = InvalidSourceTypeException::forSource('catalog.product', 'array', 'ProductInterface');

    expect($exception)->toBeInstanceOf(LayoutException::class)
        ->and($exception->getMessage())->toContain('catalog.product')
        ->and($exception->getMessage())->toContain('array')
        ->and($exception->getMessage())->toContain('ProductInterface');
});

it('includes a non-empty suggestion on every exception factory', function (): void {
    $exceptions = [
        UnknownContextException::forContext('ctx', 'layout'),
        UnknownIterationException::forIteration('loop', 'placement'),
        TypeMismatchException::forProp('prop', 'string', 'int'),
        RepeatTypeMismatchException::forItem('YieldsType', 'ActualType'),
        DanglingAnchorException::forAnchor('anchor', '/file.php'),
        DuplicateNameException::forName('name'),
        MissingDataKeyException::forKey('key', 'Component'),
        ExtensionConflictException::forConflict('opA', 'opB', 10),
        MissingPropException::forProp('prop', 'Component'),
        InvalidSourceTypeException::forSource('source', 'value', 'TargetType'),
    ];

    foreach ($exceptions as $exception) {
        expect($exception->getSuggestion())->not->toBeEmpty();
    }
});
