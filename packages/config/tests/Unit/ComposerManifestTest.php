<?php

declare(strict_types=1);

it(
    'lists markommerce/scope in packages/config/composer.json require block BEFORE this task (sanity check)',
    function (): void {
        $manifest = json_decode(
            (string) file_get_contents(dirname(__DIR__, 2) . '/composer.json'),
            true,
        );

        $require = $manifest['require'] ?? [];

        expect($require)->not->toHaveKey('markommerce/scope');
    },
);

it(
    'removes markommerce/scope from packages/config/composer.json require block',
    function (): void {
        $manifest = json_decode(
            (string) file_get_contents(dirname(__DIR__, 2) . '/composer.json'),
            true,
        );

        $require = $manifest['require'] ?? [];

        expect($require)->not->toHaveKey('markommerce/scope');
    },
);

it(
    'asserts packages/config/composer.json does NOT require markommerce/config-scope',
    function (): void {
        $manifest = json_decode(
            (string) file_get_contents(dirname(__DIR__, 2) . '/composer.json'),
            true,
        );

        $require = $manifest['require'] ?? [];

        expect($require)->not->toHaveKey('markommerce/config-scope');
    },
);

it(
    'asserts packages/config/composer.json does NOT require markommerce/config-scope-pgsql',
    function (): void {
        $manifest = json_decode(
            (string) file_get_contents(dirname(__DIR__, 2) . '/composer.json'),
            true,
        );

        $require = $manifest['require'] ?? [];

        expect($require)->not->toHaveKey('markommerce/config-scope-pgsql');
    },
);

it(
    'asserts packages/config/composer.json does NOT require markommerce/config-locale',
    function (): void {
        $manifest = json_decode(
            (string) file_get_contents(dirname(__DIR__, 2) . '/composer.json'),
            true,
        );

        $require = $manifest['require'] ?? [];

        expect($require)->not->toHaveKey('markommerce/config-locale');
    },
);

it(
    'asserts packages/config/composer.json does NOT require markommerce/config-market',
    function (): void {
        $manifest = json_decode(
            (string) file_get_contents(dirname(__DIR__, 2) . '/composer.json'),
            true,
        );

        $require = $manifest['require'] ?? [];

        expect($require)->not->toHaveKey('markommerce/config-market');
    },
);

it(
    'adds markommerce/config-scope to root composer.json require block',
    function (): void {
        $rootManifest = json_decode(
            (string) file_get_contents(dirname(__DIR__, 4) . '/composer.json'),
            true,
        );

        $require = $rootManifest['require'] ?? [];

        expect($require)->toHaveKey('markommerce/config-scope');
    },
);

it(
    'adds markommerce/config-scope-pgsql to root composer.json require block',
    function (): void {
        $rootManifest = json_decode(
            (string) file_get_contents(dirname(__DIR__, 4) . '/composer.json'),
            true,
        );

        $require = $rootManifest['require'] ?? [];

        expect($require)->toHaveKey('markommerce/config-scope-pgsql');
    },
);

it(
    'adds markommerce/config-locale to root composer.json require block',
    function (): void {
        $rootManifest = json_decode(
            (string) file_get_contents(dirname(__DIR__, 4) . '/composer.json'),
            true,
        );

        $require = $rootManifest['require'] ?? [];

        expect($require)->toHaveKey('markommerce/config-locale');
    },
);

it(
    'adds markommerce/config-market to root composer.json require block',
    function (): void {
        $rootManifest = json_decode(
            (string) file_get_contents(dirname(__DIR__, 4) . '/composer.json'),
            true,
        );

        $require = $rootManifest['require'] ?? [];

        expect($require)->toHaveKey('markommerce/config-market');
    },
);

it(
    'adds Markommerce\\ConfigScope\\Tests\\ pointing to packages/config-scope/tests/ in root autoload-dev psr-4',
    function (): void {
        $rootManifest = json_decode(
            (string) file_get_contents(dirname(__DIR__, 4) . '/composer.json'),
            true,
        );

        $autoloadDev = $rootManifest['autoload-dev']['psr-4'] ?? [];

        expect($autoloadDev)
            ->toHaveKey('Markommerce\\ConfigScope\\Tests\\')
            ->and($autoloadDev['Markommerce\\ConfigScope\\Tests\\'])
            ->toBe('packages/config-scope/tests/');
    },
);

it(
    'adds Markommerce\\ConfigLocale\\Tests\\ pointing to packages/config-locale/tests/ in root autoload-dev psr-4',
    function (): void {
        $rootManifest = json_decode(
            (string) file_get_contents(dirname(__DIR__, 4) . '/composer.json'),
            true,
        );

        $autoloadDev = $rootManifest['autoload-dev']['psr-4'] ?? [];

        expect($autoloadDev)
            ->toHaveKey('Markommerce\\ConfigLocale\\Tests\\')
            ->and($autoloadDev['Markommerce\\ConfigLocale\\Tests\\'])
            ->toBe('packages/config-locale/tests/');
    },
);

it(
    'adds Markommerce\\ConfigMarket\\Tests\\ pointing to packages/config-market/tests/ in root autoload-dev psr-4',
    function (): void {
        $rootManifest = json_decode(
            (string) file_get_contents(dirname(__DIR__, 4) . '/composer.json'),
            true,
        );

        $autoloadDev = $rootManifest['autoload-dev']['psr-4'] ?? [];

        expect($autoloadDev)
            ->toHaveKey('Markommerce\\ConfigMarket\\Tests\\')
            ->and($autoloadDev['Markommerce\\ConfigMarket\\Tests\\'])
            ->toBe('packages/config-market/tests/');
    },
);

it(
    'does NOT add Markommerce\\ConfigScope\\PgSql\\Tests\\ to root autoload-dev (per repo convention; that namespace is declared in packages/config-scope-pgsql/composer.json\'s local autoload-dev only)',
    function (): void {
        $rootManifest = json_decode(
            (string) file_get_contents(dirname(__DIR__, 4) . '/composer.json'),
            true,
        );

        $autoloadDev = $rootManifest['autoload-dev']['psr-4'] ?? [];

        expect($autoloadDev)->not->toHaveKey('Markommerce\\ConfigScope\\PgSql\\Tests\\');
    },
);
