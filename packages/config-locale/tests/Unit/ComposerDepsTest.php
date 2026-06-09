<?php

declare(strict_types=1);

it(
    'requires marko/core, markommerce/config-scope, and markommerce/locale in config-locale composer.json',
    function (): void {
        $composerPath = dirname(__DIR__, 2) . '/composer.json';
        $composer = json_decode((string) file_get_contents($composerPath), true);
    
        expect($composer['require'])->toHaveKey('marko/core')
            ->and($composer['require'])->toHaveKey('markommerce/config-scope')
            ->and($composer['require'])->toHaveKey('markommerce/locale');
    }
);
