<?php

declare(strict_types=1);

use Markommerce\Scope\Exceptions\ScopeStorageException;
use Markommerce\Scope\Storage\DefaultScopeGuard;
use Markommerce\Scope\Storage\HasScopes;

afterEach(function (): void {
    DefaultScopeGuard::reset();
});

it('allows writes to non-default-scope signatures when configured with axis defaults', function (): void {
    DefaultScopeGuard::configure(['locale' => 'global', 'geo' => 'global']);

    // Should not throw for non-default scopes
    DefaultScopeGuard::assertWritable('locale:en');
    DefaultScopeGuard::assertWritable('geo:eu.de');
    DefaultScopeGuard::assertWritable('locale:fr|geo:eu');

    expect(true)->toBeTrue();
});

it('throws ScopeStorageException when HasScopes setOverride writes at a default-scope signature', function (): void {
    DefaultScopeGuard::configure(['locale' => 'global']);

    $entity = new class ()
    {
        use HasScopes;
    };

    expect(fn () => $entity->setOverride('locale:global', 'name', 'Test'))
        ->toThrow(ScopeStorageException::class);
});

it('throws ScopeStorageException when HasScopes clearOverride targets a default-scope signature', function (): void {
    DefaultScopeGuard::configure(['locale' => 'global']);

    $entity = new class ()
    {
        use HasScopes;
    };

    expect(fn () => $entity->clearOverride('locale:global', 'name'))
        ->toThrow(ScopeStorageException::class);
});

it('rejects a multi-axis signature that names any axis at its default', function (): void {
    DefaultScopeGuard::configure(['locale' => 'global', 'geo' => 'global']);

    // 'locale:en' is non-default but 'geo:global' is the default for geo
    expect(fn () => DefaultScopeGuard::assertWritable('locale:en|geo:global'))
        ->toThrow(ScopeStorageException::class);
});

it('allows all writes when DefaultScopeGuard has not been configured', function (): void {
    // Guard not configured — should be lenient, no exception
    DefaultScopeGuard::assertWritable('locale:global');
    DefaultScopeGuard::assertWritable('geo:global');
    DefaultScopeGuard::assertWritable('locale:global|geo:global');

    expect(DefaultScopeGuard::isConfigured())->toBeFalse();
});
