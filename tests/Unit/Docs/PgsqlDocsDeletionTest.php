<?php

declare(strict_types=1);

it('docs/src/content/docs/packages/scope-pgsql.md no longer exists', function (): void {
    $file = dirname(__DIR__, 3) . '/docs/src/content/docs/packages/scope-pgsql.md';

    expect(file_exists($file))->toBeFalse(
        'docs/src/content/docs/packages/scope-pgsql.md should have been deleted but still exists',
    );
});

it('docs/src/content/docs/packages/config-pgsql.md no longer exists', function (): void {
    $file = dirname(__DIR__, 3) . '/docs/src/content/docs/packages/config-pgsql.md';

    expect(file_exists($file))->toBeFalse(
        'docs/src/content/docs/packages/config-pgsql.md should have been deleted but still exists',
    );
});

it('docs/src/content/docs/packages/config-scope-pgsql.md no longer exists', function (): void {
    $file = dirname(__DIR__, 3) . '/docs/src/content/docs/packages/config-scope-pgsql.md';

    expect(file_exists($file))->toBeFalse(
        'docs/src/content/docs/packages/config-scope-pgsql.md should have been deleted but still exists',
    );
});
