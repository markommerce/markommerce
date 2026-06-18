<?php

declare(strict_types=1);

it('ships a README.md for config-scope with the package name as H1 and an Installation section', function (): void {
    $file = __DIR__ . '/../../../packages/config-scope/README.md';

    expect(file_exists($file))->toBeTrue();

    $content = (string) file_get_contents($file);

    expect($content)
        ->toContain('# markommerce/config-scope')
        ->toContain('## Installation')
        ->toContain('composer require markommerce/config-scope');
});

it(
    'ships a README.md for config-scope with a Quick Example block showing #[Scoped(axes: [\'locale\'])] usage',
    function (): void {
        $file = __DIR__ . '/../../../packages/config-scope/README.md';
    
        expect(file_exists($file))->toBeTrue();
    
        $content = (string) file_get_contents($file);
    
        expect($content)
            ->toContain('## Quick Example')
            ->toContain('#[Scoped(axes:')
            ->toContain('## Documentation')
            ->toContain('markommerce.dev/docs/packages/config-scope');
    }
);

it(
    'confirms packages/config-scope-pgsql has been merged into packages/config-scope and the directory is deleted',
    function (): void {
        $dir = __DIR__ . '/../../../packages/config-scope-pgsql';
    
        expect(is_dir($dir))->toBeFalse();
    }
);

it(
    'ships a README.md for config-locale documenting the placeholder status and the merchant-#[Scoped] shortcut',
    function (): void {
        $file = __DIR__ . '/../../../packages/config-locale/README.md';
    
        expect(file_exists($file))->toBeTrue();
    
        $content = (string) file_get_contents($file);
    
        expect($content)
            ->toContain('# markommerce/config-locale')
            ->toContain('## Installation')
            ->toContain('composer require markommerce/config-locale')
            ->toContain('placeholder')
            ->toContain('#[Scoped')
            ->toContain('## Documentation')
            ->toContain('markommerce.dev/docs/packages/config-locale');
    }
);

it('ships a README.md for config-market documenting the placeholder status', function (): void {
    $file = __DIR__ . '/../../../packages/config-market/README.md';

    expect(file_exists($file))->toBeTrue();

    $content = (string) file_get_contents($file);

    expect($content)
        ->toContain('# markommerce/config-market')
        ->toContain('## Installation')
        ->toContain('composer require markommerce/config-market')
        ->toContain('placeholder')
        ->toContain('## Documentation')
        ->toContain('markommerce.dev/docs/packages/config-market');
});

it('removes the #[Scoped] example from packages/config/README.md', function (): void {
    $file = __DIR__ . '/../../../packages/config/README.md';

    expect(file_exists($file))->toBeTrue();

    $content = (string) file_get_contents($file);

    expect($content)
        ->toContain('# markommerce/config')
        ->not->toContain('#[Scoped(');
});

it(
    'confirms packages/config-pgsql has been merged into packages/config and the directory is deleted',
    function (): void {
        $dir = __DIR__ . '/../../../packages/config-pgsql';
    
        expect(is_dir($dir))->toBeFalse();
    }
);

it(
    'ships a docs page at docs/src/content/docs/packages/config-scope.md (asserted by ConfigScopeDecouplePagesTest)',
    function (): void {
        $file = __DIR__ . '/../../../docs/src/content/docs/packages/config-scope.md';
    
        expect(file_exists($file))->toBeTrue();
    
        $content = (string) file_get_contents($file);
    
        expect($content)->toMatch('/^---\ntitle:/');
        expect($content)->toContain('title: markommerce/config-scope');
        expect($content)->toContain('description:');
        expect($content)->not->toContain('## Overview');
    
        $withoutFrontmatter = preg_replace('/^---.*?---\n/s', '', $content);
        expect(trim($withoutFrontmatter))->not->toBeEmpty();
    
        expect($content)->toContain('## Installation');
        expect($content)->toContain('composer require markommerce/config-scope');
        expect($content)->toContain('## Usage');
        expect($content)->toContain('#[Scoped(axes:');
        expect($content)->toContain('## Related Packages');
        expect($content)->toContain('markommerce/config');
        expect($content)->toContain('markommerce/scope');
    }
);

it(
    'confirms docs/src/content/docs/packages/config-scope-pgsql.md has been removed (package merged into config-scope)',
    function (): void {
        $file = __DIR__ . '/../../../docs/src/content/docs/packages/config-scope-pgsql.md';

        expect(file_exists($file))->toBeFalse(
            'config-scope-pgsql.md should have been deleted since config-scope-pgsql was merged into config-scope',
        );
    }
);

it(
    'ships a docs page at docs/src/content/docs/packages/config-locale.md (asserted by ConfigScopeDecouplePagesTest)',
    function (): void {
        $file = __DIR__ . '/../../../docs/src/content/docs/packages/config-locale.md';
    
        expect(file_exists($file))->toBeTrue();
    
        $content = (string) file_get_contents($file);
    
        expect($content)->toMatch('/^---\ntitle:/');
        expect($content)->toContain('title: markommerce/config-locale');
        expect($content)->toContain('description:');
        expect($content)->not->toContain('## Overview');
    
        $withoutFrontmatter = preg_replace('/^---.*?---\n/s', '', $content);
        expect(trim($withoutFrontmatter))->not->toBeEmpty();
    
        expect($content)->toContain('## Installation');
        expect($content)->toContain('composer require markommerce/config-locale');
        expect($content)->toContain('placeholder');
        expect($content)->toContain('## Related Packages');
        expect($content)->toContain('markommerce/config-scope');
        expect($content)->toContain('markommerce/locale');
    }
);

it(
    'ships a docs page at docs/src/content/docs/packages/config-market.md (asserted by ConfigScopeDecouplePagesTest)',
    function (): void {
        $file = __DIR__ . '/../../../docs/src/content/docs/packages/config-market.md';
    
        expect(file_exists($file))->toBeTrue();
    
        $content = (string) file_get_contents($file);
    
        expect($content)->toMatch('/^---\ntitle:/');
        expect($content)->toContain('title: markommerce/config-market');
        expect($content)->toContain('description:');
        expect($content)->not->toContain('## Overview');
    
        $withoutFrontmatter = preg_replace('/^---.*?---\n/s', '', $content);
        expect(trim($withoutFrontmatter))->not->toBeEmpty();
    
        expect($content)->toContain('## Installation');
        expect($content)->toContain('composer require markommerce/config-market');
        expect($content)->toContain('placeholder');
        expect($content)->toContain('## Related Packages');
        expect($content)->toContain('markommerce/config-scope');
        expect($content)->toContain('markommerce/market');
    }
);

it(
    'updates docs/src/content/docs/packages/config.md to remove scope-aware examples (asserted by ConfigScopeDecouplePagesTest)',
    function (): void {
        $file = __DIR__ . '/../../../docs/src/content/docs/packages/config.md';
    
        expect(file_exists($file))->toBeTrue();
    
        $content = (string) file_get_contents($file);
    
        expect($content)->not->toContain('#[Scoped(');
        expect($content)->toContain('## Related Packages');
        expect($content)->toContain('markommerce/config-scope');
    }
);
