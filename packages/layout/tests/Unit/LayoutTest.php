<?php

declare(strict_types=1);

use Markommerce\Layout\Contracts\Operation;
use Markommerce\Layout\Layout;
use Markommerce\Layout\Operation\Remove;

it('passes PHPStan level 8 with the new fields typed', function (): void {
    $output = shell_exec('cd /workspace/markommerce && ./vendor/bin/phpstan analyse packages/layout/src/Layout.php --level=8 --no-progress 2>&1');

    expect($output)->toContain('[OK] No errors');
});

it('rejects an inherits value that equals the layout\'s own handle key', function (): void {
    expect(fn() => new Layout(
        handle: 'some-handle',
        extends: null,
        context: [],
        slots: [],
        inherits: 'some-handle',
    ))->toThrow(InvalidArgumentException::class);

    expect(fn() => new Layout(
        handle: ['App\Controller\FooController', 'show'],
        extends: null,
        context: [],
        slots: [],
        inherits: 'App\Controller\FooController::show',
    ))->toThrow(InvalidArgumentException::class);
});

it('preserves backwards compatibility with layouts that omit the new fields', function (): void {
    $layout = new Layout(
        handle: 'legacy-handle',
        extends: null,
        context: [],
        slots: [],
        template: 'one_column',
    );

    expect($layout->handle)->toBe('legacy-handle')
        ->and($layout->extends)->toBeNull()
        ->and($layout->inherits)->toBeNull()
        ->and($layout->context)->toBe([])
        ->and($layout->slots)->toBe([])
        ->and($layout->operations)->toBe([])
        ->and($layout->template)->toBe('one_column');
});

it('stores both fields as readonly public properties', function (): void {
    $reflection = new ReflectionClass(Layout::class);

    $inheritsProperty = $reflection->getProperty('inherits');
    expect($inheritsProperty->isPublic())->toBeTrue()
        ->and($inheritsProperty->isReadOnly())->toBeTrue();

    $operationsProperty = $reflection->getProperty('operations');
    expect($operationsProperty->isPublic())->toBeTrue()
        ->and($operationsProperty->isReadOnly())->toBeTrue();
});

it('accepts an operations parameter as list of Operation defaulting to empty array', function (): void {
    $layout = new Layout(
        handle: 'some-handle',
        extends: null,
        context: [],
        slots: [],
    );

    expect($layout->operations)->toBe([]);

    $remove = new Remove('some-placement');
    $layoutWithOperations = new Layout(
        handle: 'some-handle',
        extends: null,
        context: [],
        slots: [],
        operations: [$remove],
    );

    expect($layoutWithOperations->operations)->toBe([$remove]);
});

it('accepts an inherits parameter as nullable string defaulting to null', function (): void {
    $layout = new Layout(
        handle: 'some-handle',
        extends: null,
        context: [],
        slots: [],
    );

    expect($layout->inherits)->toBeNull();

    $layoutWithInherits = new Layout(
        handle: 'some-handle',
        extends: null,
        context: [],
        slots: [],
        inherits: 'parent-handle',
    );

    expect($layoutWithInherits->inherits)->toBe('parent-handle');
});
