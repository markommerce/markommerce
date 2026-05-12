<?php

declare(strict_types=1);

it('creates a composer.json for markommerce/money with type library and no moneyphp dependency', function (): void {
    $composerPath = dirname(__DIR__, 2) . '/composer.json';

    expect(file_exists($composerPath))->toBeTrue('composer.json should exist');

    $composer = json_decode((string) file_get_contents($composerPath), true);

    expect(json_last_error())->toBe(JSON_ERROR_NONE, 'composer.json should be valid JSON')
        ->and($composer['name'])->toBe('markommerce/money')
        ->and($composer['type'])->toBe('library')
        ->and($composer['license'])->toBe('MIT');

    expect(isset($composer['require']['moneyphp/money']))->toBeFalse('moneyphp/money must not be in require');
    expect(isset($composer['require-dev']['moneyphp/money']))->toBeFalse('moneyphp/money must not be in require-dev');
});

it('declares the autoload namespace as Markommerce\Money and the dev namespace as Markommerce\Money\Tests', function (): void {
    $composerPath = dirname(__DIR__, 2) . '/composer.json';
    $composer = json_decode((string) file_get_contents($composerPath), true);

    expect($composer['autoload']['psr-4'])->toHaveKey('Markommerce\\Money\\')
        ->and($composer['autoload']['psr-4']['Markommerce\\Money\\'])->toBe('src/');

    expect($composer['autoload-dev']['psr-4'])->toHaveKey('Markommerce\\Money\\Tests\\')
        ->and($composer['autoload-dev']['psr-4']['Markommerce\\Money\\Tests\\'])->toBe('tests/');
});

it('requires php 8.5 and marko/core but does not require moneyphp or ext-intl', function (): void {
    $composerPath = dirname(__DIR__, 2) . '/composer.json';
    $composer = json_decode((string) file_get_contents($composerPath), true);

    expect($composer['require'])->toHaveKey('php')
        ->and($composer['require']['php'])->toBe('^8.5')
        ->and($composer['require'])->toHaveKey('marko/core')
        ->and($composer['require']['marko/core'])->toBe('self.version');

    expect(isset($composer['require']['moneyphp/money']))->toBeFalse('moneyphp/money must not be required');
    expect(isset($composer['require']['ext-intl']))->toBeFalse('ext-intl must not be required');
});

it('is wired into the root composer.json require block as markommerce/money self.version', function (): void {
    $rootComposerPath = dirname(__DIR__, 4) . '/composer.json';

    expect(file_exists($rootComposerPath))->toBeTrue('root composer.json should exist');

    $rootComposer = json_decode((string) file_get_contents($rootComposerPath), true);

    expect($rootComposer['require'])->toHaveKey('markommerce/money')
        ->and($rootComposer['require']['markommerce/money'])->toBe('self.version');
});

it('declares MoneyInterface with amount currency add subtract multiply(string) allocate equals greaterThan lessThan isZero and format methods returning MoneyInterface or matching types', function (): void {
    $interfaceName = 'Markommerce\\Money\\MoneyInterface';

    expect(interface_exists($interfaceName))->toBeTrue('MoneyInterface must exist');

    $reflection = new ReflectionClass($interfaceName);

    expect($reflection->isInterface())->toBeTrue();

    // Check all required methods exist
    $requiredMethods = ['amount', 'currency', 'add', 'subtract', 'multiply', 'allocate', 'equals', 'greaterThan', 'lessThan', 'isZero', 'format'];
    foreach ($requiredMethods as $method) {
        expect($reflection->hasMethod($method))->toBeTrue("MoneyInterface must have method: {$method}");
    }

    // amount(): int
    $amount = $reflection->getMethod('amount');
    expect($amount->getReturnType()?->getName())->toBe('int');

    // currency(): string
    $currency = $reflection->getMethod('currency');
    expect($currency->getReturnType()?->getName())->toBe('string');

    // add(MoneyInterface $other): MoneyInterface
    $add = $reflection->getMethod('add');
    expect($add->getReturnType()?->getName())->toBe($interfaceName);
    $addParams = $add->getParameters();
    expect(count($addParams))->toBe(1)
        ->and($addParams[0]->getType()?->getName())->toBe($interfaceName);

    // subtract(MoneyInterface $other): MoneyInterface
    $subtract = $reflection->getMethod('subtract');
    expect($subtract->getReturnType()?->getName())->toBe($interfaceName);

    // multiply(string $factor): MoneyInterface
    $multiply = $reflection->getMethod('multiply');
    expect($multiply->getReturnType()?->getName())->toBe($interfaceName);
    $multiplyParams = $multiply->getParameters();
    expect(count($multiplyParams))->toBe(1)
        ->and($multiplyParams[0]->getType()?->getName())->toBe('string');

    // allocate(array $ratios): array
    $allocate = $reflection->getMethod('allocate');
    expect($allocate->getReturnType()?->getName())->toBe('array');
    $allocateParams = $allocate->getParameters();
    expect(count($allocateParams))->toBe(1)
        ->and($allocateParams[0]->getType()?->getName())->toBe('array');

    // equals(MoneyInterface $other): bool
    $equals = $reflection->getMethod('equals');
    expect($equals->getReturnType()?->getName())->toBe('bool');

    // greaterThan(MoneyInterface $other): bool
    $greaterThan = $reflection->getMethod('greaterThan');
    expect($greaterThan->getReturnType()?->getName())->toBe('bool');

    // lessThan(MoneyInterface $other): bool
    $lessThan = $reflection->getMethod('lessThan');
    expect($lessThan->getReturnType()?->getName())->toBe('bool');

    // isZero(): bool
    $isZero = $reflection->getMethod('isZero');
    expect($isZero->getReturnType()?->getName())->toBe('bool');

    // format(?string $locale = null): string
    $format = $reflection->getMethod('format');
    expect($format->getReturnType()?->getName())->toBe('string');
    $formatParams = $format->getParameters();
    expect(count($formatParams))->toBe(1)
        ->and($formatParams[0]->isOptional())->toBeTrue()
        ->and($formatParams[0]->getDefaultValue())->toBeNull();

    // Return types for add/subtract must not be 'self' or 'static'
    expect($add->getReturnType()?->getName())->not->toBe('self')
        ->and($add->getReturnType()?->getName())->not->toBe('static');
});

it('declares MoneyFactoryInterface with a single create(int amount, ?string currency = null) method returning MoneyInterface', function (): void {
    $interfaceName = 'Markommerce\\Money\\MoneyFactoryInterface';

    expect(interface_exists($interfaceName))->toBeTrue('MoneyFactoryInterface must exist');

    $reflection = new ReflectionClass($interfaceName);
    expect($reflection->isInterface())->toBeTrue();

    expect($reflection->hasMethod('create'))->toBeTrue('MoneyFactoryInterface must have create method');

    $create = $reflection->getMethod('create');
    expect($create->getReturnType()?->getName())->toBe('Markommerce\\Money\\MoneyInterface');

    $params = $create->getParameters();
    expect(count($params))->toBe(2);

    // First param: int $amount
    expect($params[0]->getName())->toBe('amount')
        ->and($params[0]->getType()?->getName())->toBe('int');

    // Second param: ?string $currency = null
    expect($params[1]->getName())->toBe('currency')
        ->and($params[1]->isOptional())->toBeTrue()
        ->and($params[1]->getDefaultValue())->toBeNull();

    // Verify nullable string type
    $currencyType = $params[1]->getType();
    expect($currencyType)->toBeInstanceOf(ReflectionNamedType::class);
    expect($currencyType->allowsNull())->toBeTrue();
    expect($currencyType->getName())->toBe('string');
});

it('declares CurrencyConfigInterface with a single getDefault method returning a string', function (): void {
    $interfaceName = 'Markommerce\\Money\\CurrencyConfigInterface';

    expect(interface_exists($interfaceName))->toBeTrue('CurrencyConfigInterface must exist');

    $reflection = new ReflectionClass($interfaceName);
    expect($reflection->isInterface())->toBeTrue();

    expect($reflection->hasMethod('getDefault'))->toBeTrue('CurrencyConfigInterface must have getDefault method');

    $getDefault = $reflection->getMethod('getDefault');
    expect($getDefault->getReturnType()?->getName())->toBe('string');
    expect(count($getDefault->getParameters()))->toBe(0);
});

it('carries a multi-store refactor docblock on CurrencyConfigInterface', function (): void {
    $interfaceName = 'Markommerce\\Money\\CurrencyConfigInterface';

    expect(interface_exists($interfaceName))->toBeTrue('CurrencyConfigInterface must exist');

    $reflection = new ReflectionClass($interfaceName);

    $docComment = $reflection->getDocComment();
    expect($docComment)->not->toBeFalse('CurrencyConfigInterface must have a doc comment');
    expect($docComment)->toContain('@todo multi-store');
});
