<?php

declare(strict_types=1);

use Markommerce\Layout\Compiler\ResolutionPhase;
use Markommerce\Layout\Contracts\LayoutDefinition;
use Markommerce\Layout\Discovery\DiscoveredExtension;
use Markommerce\Layout\Discovery\DiscoveredLayout;
use Markommerce\Layout\Discovery\DiscoveryResult;
use Markommerce\Layout\Exceptions\DefaultHandleConflictException;
use Markommerce\Layout\Exceptions\DuplicateContextTokenException;
use Markommerce\Layout\Layout;
use Markommerce\Layout\LayoutExtension;
use Markommerce\Layout\Operation\Append;
use Markommerce\Layout\Operation\Remove;
use Markommerce\Layout\Place;
use Markommerce\Layout\Provide;
use Markommerce\Layout\ProvideHandle;

// =============================================================================
// Helpers
// =============================================================================

function makeDefaultDiscoveryResult(array $layouts, array $extensions = []): DiscoveryResult
{
    $discoveredLayouts = array_map(
        fn (Layout $layout) => new DiscoveredLayout($layout, 'fake://file.php'),
        $layouts,
    );
    $discoveredExtensions = array_map(
        fn (LayoutExtension $ext) => new DiscoveredExtension($ext, 'fake://ext.php'),
        $extensions,
    );

    return new DiscoveryResult(
        layouts: array_values($discoveredLayouts),
        extensions: array_values($discoveredExtensions),
    );
}

// =============================================================================
// Task 027: Default handle compile-time merge
// =============================================================================

it('merges default-handle placements into every other handle at compile time', function (): void {
    $defaultLayout = new Layout(
        handle: 'default',
        extends: null,
        context: [],
        slots: ['main' => [new Place('DefaultHeader', 'default-header', [], [])]],
    );
    $pageLayout = new Layout(
        handle: 'page.index',
        extends: null,
        context: [],
        slots: ['main' => [new Place('PageContent', 'page-content', [], [])]],
    );

    $result = (new ResolutionPhase())->resolve(
        makeDefaultDiscoveryResult([$defaultLayout, $pageLayout]),
    );

    expect($result)->toHaveKey('page.index')
        ->and($result['page.index']->slots['main'])->toHaveCount(2)
        ->and($result['page.index']->slots['main'][0]->component)->toBe('DefaultHeader')
        ->and($result['page.index']->slots['main'][1]->component)->toBe('PageContent');
});

it('prepends default placements to existing slot entries', function (): void {
    $defaultLayout = new Layout(
        handle: 'default',
        extends: null,
        context: [],
        slots: ['main' => [
            new Place('DefaultFirst', 'default-first', [], []),
            new Place('DefaultSecond', 'default-second', [], []),
        ]],
    );
    $pageLayout = new Layout(
        handle: 'page.show',
        extends: null,
        context: [],
        slots: ['main' => [new Place('OwnContent', 'own-content', [], [])]],
    );

    $result = (new ResolutionPhase())->resolve(
        makeDefaultDiscoveryResult([$defaultLayout, $pageLayout]),
    );

    expect($result['page.show']->slots['main'])->toHaveCount(3)
        ->and($result['page.show']->slots['main'][0]->component)->toBe('DefaultFirst')
        ->and($result['page.show']->slots['main'][1]->component)->toBe('DefaultSecond')
        ->and($result['page.show']->slots['main'][2]->component)->toBe('OwnContent');
});

it('applies default operations across every other handle in the final pass', function (): void {
    $defaultLayout = new Layout(
        handle: 'default',
        extends: null,
        context: [],
        slots: ['main' => [new Place('DefaultBanner', 'default-banner', [], [])]],
        operations: [new Append('main', new Place('DefaultFooter', 'default-footer', [], []))],
    );
    $pageLayout = new Layout(
        handle: 'page.list',
        extends: null,
        context: [],
        slots: ['main' => [new Place('PageContent', 'page-content', [], [])]],
    );

    $result = (new ResolutionPhase())->resolve(
        makeDefaultDiscoveryResult([$defaultLayout, $pageLayout]),
    );

    // After Half A: main = [default-banner, page-content]
    // After Half B (default Append 'default-footer'): main = [default-banner, page-content, default-footer]
    expect($result['page.list']->slots['main'])->toHaveCount(3)
        ->and($result['page.list']->slots['main'][0]->component)->toBe('DefaultBanner')
        ->and($result['page.list']->slots['main'][1]->component)->toBe('PageContent')
        ->and($result['page.list']->slots['main'][2]->component)->toBe('DefaultFooter');
});

it(
    'allows a sibling\'s own operations to Remove a placement contributed by default (Half A merges placements first)',
    function (): void {
        $defaultLayout = new Layout(
            handle: 'default',
            extends: null,
            context: [],
            slots: ['main' => [new Place('DefaultHeader', 'default-header', [], [])]],
        );
        $pageLayout = new Layout(
            handle: 'page.override',
            extends: null,
            context: [],
            slots: ['main' => [new Place('OwnContent', 'own-content', [], [])]],
            operations: [new Remove('default-header')],
        );

        $result = (new ResolutionPhase())->resolve(
            makeDefaultDiscoveryResult([$defaultLayout, $pageLayout]),
        );

        // The sibling removes the default-provided 'default-header'
        expect($result['page.override']->slots['main'])->toHaveCount(1)
                ->and($result['page.override']->slots['main'][0]->component)->toBe('OwnContent');
    },
);

it(
    'applies default operations only once even when a handle inherits from another handle (no double-merge via inherits)',
    function (): void {
        $defaultLayout = new Layout(
            handle: 'default',
            extends: null,
            context: [],
            slots: ['main' => [new Place('DefaultHeader', 'default-header', [], [])]],
        );
        $parentLayout = new Layout(
            handle: 'parent.handle',
            extends: null,
            context: [],
            slots: ['main' => [new Place('ParentContent', 'parent-content', [], [])]],
        );
        $childLayout = new Layout(
            handle: 'child.handle',
            extends: null,
            inherits: 'parent.handle',
            context: [],
            slots: ['main' => [new Place('ChildContent', 'child-content', [], [])]],
        );

        $result = (new ResolutionPhase())->resolve(
            makeDefaultDiscoveryResult([$defaultLayout, $parentLayout, $childLayout]),
        );

        // child.handle should have: [default-header, parent-content, child-content]
        // NOT: [default-header, default-header, parent-content, child-content] (double-merge)
        expect($result['child.handle']->slots['main'])->toHaveCount(3)
                ->and($result['child.handle']->slots['main'][0]->component)->toBe('DefaultHeader')
                ->and($result['child.handle']->slots['main'][1]->component)->toBe('ParentContent')
                ->and($result['child.handle']->slots['main'][2]->component)->toBe('ChildContent');
    },
);

it('omits the default key from the final runtime artifact', function (): void {
    $defaultLayout = new Layout(
        handle: 'default',
        extends: null,
        context: [],
        slots: ['main' => [new Place('DefaultHeader', 'default-header', [], [])]],
    );
    $pageLayout = new Layout(
        handle: 'page.index',
        extends: null,
        context: [],
        slots: ['main' => [new Place('PageContent', 'page-content', [], [])]],
    );

    $result = (new ResolutionPhase())->resolve(
        makeDefaultDiscoveryResult([$defaultLayout, $pageLayout]),
    );

    expect($result)->not->toHaveKey('default')
        ->and($result)->toHaveKey('page.index');
});

it('throws DefaultHandleConflictException when default declares extends', function (): void {
    // We need a LayoutDefinition stub — use the one from ResolutionPhaseTest if accessible,
    // or define our own here.
    $defaultLayout = new Layout(
        handle: 'default',
        extends: LayoutDefinition::class,
        context: [],
        slots: [],
    );

    expect(fn () => (new ResolutionPhase())->resolve(
        makeDefaultDiscoveryResult([$defaultLayout]),
    ))->toThrow(DefaultHandleConflictException::class);
});

it('throws DefaultHandleConflictException when default declares inherits', function (): void {
    $parentLayout = new Layout(
        handle: 'some.parent',
        extends: null,
        context: [],
        slots: [],
    );
    $defaultLayout = new Layout(
        handle: 'default',
        extends: null,
        inherits: 'some.parent',
        context: [],
        slots: [],
    );

    expect(fn () => (new ResolutionPhase())->resolve(
        makeDefaultDiscoveryResult([$parentLayout, $defaultLayout]),
    ))->toThrow(DefaultHandleConflictException::class);
});

it('throws DefaultHandleConflictException when default declares handleProviders', function (): void {
    $defaultLayout = new Layout(
        handle: 'default',
        extends: null,
        context: [],
        slots: [],
        handleProviders: [new ProvideHandle('SomeProvider', [])],
    );

    expect(fn () => (new ResolutionPhase())->resolve(
        makeDefaultDiscoveryResult([$defaultLayout]),
    ))->toThrow(DefaultHandleConflictException::class);
});

it(
    'throws DuplicateContextTokenException when default and a sibling declare the same context token',
    function (): void {
        $sharedProvide = new Provide('SharedToken', 'SomeProvider', []);

        $defaultLayout = new Layout(
            handle: 'default',
            extends: null,
            context: [$sharedProvide],
            slots: [],
        );
        $pageLayout = new Layout(
            handle: 'page.conflict',
            extends: null,
            context: [$sharedProvide],
            slots: [],
        );

        expect(fn () => (new ResolutionPhase())->resolve(
            makeDefaultDiscoveryResult([$defaultLayout, $pageLayout]),
        ))->toThrow(DuplicateContextTokenException::class);
    },
);
