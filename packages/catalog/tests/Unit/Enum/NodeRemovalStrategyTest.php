<?php

declare(strict_types=1);

use Markommerce\Catalog\Enum\NodeRemovalStrategy;

it('defines a CASCADE case', function (): void {
    expect(NodeRemovalStrategy::CASCADE)->toBeInstanceOf(NodeRemovalStrategy::class);
});

it('defines a PROMOTE_CHILDREN case', function (): void {
    expect(NodeRemovalStrategy::PROMOTE_CHILDREN)->toBeInstanceOf(NodeRemovalStrategy::class);
});

it('has exactly two cases', function (): void {
    expect(NodeRemovalStrategy::cases())->toHaveCount(2);
});

it('is a pure enum without a backing type', function (): void {
    $reflection = new ReflectionEnum(NodeRemovalStrategy::class);

    expect($reflection->getBackingType())->toBeNull();
});
