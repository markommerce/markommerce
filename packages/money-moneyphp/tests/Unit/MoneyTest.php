<?php

declare(strict_types=1);

it('creates a composer.json for markommerce/money-moneyphp with type marko-module and requires moneyphp ext-intl markommerce/money and marko/core', function (): void {
    $composerPath = dirname(__DIR__, 2) . '/composer.json';

    expect(file_exists($composerPath))->toBeTrue('composer.json should exist');

    $composer = json_decode((string) file_get_contents($composerPath), true);

    expect(json_last_error())->toBe(JSON_ERROR_NONE, 'composer.json should be valid JSON')
        ->and($composer['name'])->toBe('markommerce/money-moneyphp')
        ->and($composer['type'])->toBe('marko-module')
        ->and($composer['license'])->toBe('MIT');

    expect($composer['require'])->toHaveKey('moneyphp/money');
    expect($composer['require'])->toHaveKey('ext-intl');
    expect($composer['require'])->toHaveKey('markommerce/money');
    expect($composer['require'])->toHaveKey('marko/core');
});

it('formats the amount using IntlMoneyFormatter with the provided locale and falls back to Locale getDefault when none is given', function (): void {
    \Locale::setDefault('en_US');

    $money = new \Markommerce\Money\Moneyphp\Money(1000, 'USD');

    // Explicit locale
    $formatted = $money->format('en_US');
    expect($formatted)->toContain('$')
        ->and($formatted)->toContain('10');

    // Falls back to Locale::getDefault() (which we set to en_US)
    $formattedDefault = $money->format();
    expect($formattedDefault)->toContain('$')
        ->and($formattedDefault)->toContain('10');

    // Different locale
    $formattedDe = $money->format('de_DE');
    // German formatting uses comma as decimal separator and different currency symbol position
    expect($formattedDe)->toBeString()
        ->and(strlen($formattedDe))->toBeGreaterThan(0);
});

it('reports whether the amount is zero', function (): void {
    $zero = new \Markommerce\Money\Moneyphp\Money(0, 'USD');
    $nonZero = new \Markommerce\Money\Moneyphp\Money(1, 'USD');

    expect($zero->isZero())->toBeTrue()
        ->and($nonZero->isZero())->toBeFalse();
});

it('throws MoneyException currencyMismatch from greaterThan and lessThan on currency mismatch', function (): void {
    $usd = new \Markommerce\Money\Moneyphp\Money(1000, 'USD');
    $eur = new \Markommerce\Money\Moneyphp\Money(1000, 'EUR');

    expect(fn () => $usd->greaterThan($eur))
        ->toThrow(\Markommerce\Money\MoneyException::class);

    expect(fn () => $usd->lessThan($eur))
        ->toThrow(\Markommerce\Money\MoneyException::class);
});

it('returns false from equals when currencies differ rather than throwing', function (): void {
    $a = new \Markommerce\Money\Moneyphp\Money(1000, 'USD');
    $b = new \Markommerce\Money\Moneyphp\Money(1000, 'EUR');

    // Should NOT throw — just return false
    expect($a->equals($b))->toBeFalse();
});

it('returns equality only when both amount and currency match', function (): void {
    $a = new \Markommerce\Money\Moneyphp\Money(1000, 'USD');
    $b = new \Markommerce\Money\Moneyphp\Money(1000, 'USD');
    $c = new \Markommerce\Money\Moneyphp\Money(500, 'USD');

    expect($a->equals($b))->toBeTrue()
        ->and($a->equals($c))->toBeFalse();
});

it('throws MoneyException invalidAllocationRatios for empty non-positive or zero-sum ratios', function (): void {
    $money = new \Markommerce\Money\Moneyphp\Money(1000, 'USD');

    // Empty array
    expect(fn () => $money->allocate([]))
        ->toThrow(\Markommerce\Money\MoneyException::class);

    // Non-positive ratio
    expect(fn () => $money->allocate([1, 0, 1]))
        ->toThrow(\Markommerce\Money\MoneyException::class);

    expect(fn () => $money->allocate([-1, 1]))
        ->toThrow(\Markommerce\Money\MoneyException::class);
});

it('allocates an amount across positive ratios distributing remainder deterministically', function (): void {
    $money = new \Markommerce\Money\Moneyphp\Money(1000, 'USD');

    $parts = $money->allocate([1, 1]);

    expect(count($parts))->toBe(2);
    expect($parts[0])->toBeInstanceOf(\Markommerce\Money\MoneyInterface::class);
    expect($parts[0]->amount())->toBe(500);
    expect($parts[1]->amount())->toBe(500);
    expect($parts[0]->currency())->toBe('USD');

    // Remainder distributed deterministically: 1000 in 3 parts = [334, 333, 333]
    $parts2 = $money->allocate([1, 1, 1]);
    $total = array_sum(array_map(fn ($m) => $m->amount(), $parts2));
    expect($total)->toBe(1000);
    expect(count($parts2))->toBe(3);
});

it('throws MoneyException invalidMultiplyFactor when the factor is not a valid numeric string', function (): void {
    $money = new \Markommerce\Money\Moneyphp\Money(1000, 'USD');

    expect(fn () => $money->multiply('not-a-number'))
        ->toThrow(\Markommerce\Money\MoneyException::class);

    expect(fn () => $money->multiply('abc'))
        ->toThrow(\Markommerce\Money\MoneyException::class);
});

it('multiplies by a numeric string factor preserving precision', function (): void {
    $money = new \Markommerce\Money\Moneyphp\Money(1000, 'USD');

    $result = $money->multiply('1.5');

    expect($result->amount())->toBe(1500)
        ->and($result->currency())->toBe('USD');

    $result2 = $money->multiply('2');
    expect($result2->amount())->toBe(2000);
});

it('subtracts two Money instances of the same currency', function (): void {
    $a = new \Markommerce\Money\Moneyphp\Money(1000, 'USD');
    $b = new \Markommerce\Money\Moneyphp\Money(300, 'USD');

    $result = $a->subtract($b);

    expect($result->amount())->toBe(700)
        ->and($result->currency())->toBe('USD');
});

it('throws MoneyException currencyMismatch from add when operand currency differs', function (): void {
    $a = new \Markommerce\Money\Moneyphp\Money(1000, 'USD');
    $b = new \Markommerce\Money\Moneyphp\Money(500, 'EUR');

    expect(fn () => $a->add($b))
        ->toThrow(\Markommerce\Money\MoneyException::class);
});

it('adds correctly when the operand is a different MoneyInterface implementation', function (): void {
    $a = new \Markommerce\Money\Moneyphp\Money(1000, 'USD');

    // Fake MoneyInterface implementation
    $other = new class (300, 'USD') implements \Markommerce\Money\MoneyInterface {
        public function __construct(private int $amount, private string $currency) {}
        public function amount(): int { return $this->amount; }
        public function currency(): string { return $this->currency; }
        public function add(\Markommerce\Money\MoneyInterface $money): \Markommerce\Money\MoneyInterface { return $this; }
        public function subtract(\Markommerce\Money\MoneyInterface $money): \Markommerce\Money\MoneyInterface { return $this; }
        public function multiply(string $factor): \Markommerce\Money\MoneyInterface { return $this; }
        public function allocate(array $ratios): array { return []; }
        public function equals(\Markommerce\Money\MoneyInterface $money): bool { return false; }
        public function greaterThan(\Markommerce\Money\MoneyInterface $money): bool { return false; }
        public function lessThan(\Markommerce\Money\MoneyInterface $money): bool { return false; }
        public function isZero(): bool { return false; }
        public function format(?string $locale = null): string { return ''; }
    };

    $result = $a->add($other);

    expect($result->amount())->toBe(1300)
        ->and($result->currency())->toBe('USD');
});

it('adds two Money instances of the same currency and returns a new MoneyInterface', function (): void {
    $a = new \Markommerce\Money\Moneyphp\Money(1000, 'USD');
    $b = new \Markommerce\Money\Moneyphp\Money(500, 'USD');

    $result = $a->add($b);

    expect($result)->toBeInstanceOf(\Markommerce\Money\MoneyInterface::class)
        ->and($result->amount())->toBe(1500)
        ->and($result->currency())->toBe('USD');

    // Original instances unchanged (immutable value object)
    expect($a->amount())->toBe(1000);
});

it('exposes the original amount and currency via amount and currency accessors', function (): void {
    $money = new \Markommerce\Money\Moneyphp\Money(4250, 'EUR');

    expect($money->amount())->toBe(4250)
        ->and($money->currency())->toBe('EUR');
});

it('constructs a Money with an integer minor-unit amount and an ISO 4217 currency', function (): void {
    $money = new \Markommerce\Money\Moneyphp\Money(1000, 'USD');

    expect($money)->toBeInstanceOf(\Markommerce\Money\MoneyInterface::class);
    expect($money->amount())->toBe(1000);
    expect($money->currency())->toBe('USD');
});

it('implements MoneyInterface in a readonly Money class wrapping a private moneyphp Money instance', function (): void {
    $className = 'Markommerce\\Money\\Moneyphp\\Money';

    expect(class_exists($className))->toBeTrue('Money class must exist');

    $reflection = new ReflectionClass($className);

    expect($reflection->isReadOnly())->toBeTrue('Money must be a readonly class')
        ->and($reflection->implementsInterface('Markommerce\\Money\\MoneyInterface'))->toBeTrue('Money must implement MoneyInterface')
        ->and($reflection->isFinal())->toBeFalse('Money must not be final');

    // Verify moneyphp Money instance is NOT publicly accessible
    $publicProperties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);
    foreach ($publicProperties as $prop) {
        expect($prop->getType()?->getName())->not->toBe('Money\\Money', 'moneyphp Money must not be exposed publicly');
    }
});

it('declares the autoload namespace as Markommerce\Money\Moneyphp and the dev namespace under it (nested under the contract package namespace per the Marko\Database\MySql precedent)', function (): void {
    $composerPath = dirname(__DIR__, 2) . '/composer.json';
    $composer = json_decode((string) file_get_contents($composerPath), true);

    expect($composer['autoload']['psr-4'])->toHaveKey('Markommerce\\Money\\Moneyphp\\')
        ->and($composer['autoload']['psr-4']['Markommerce\\Money\\Moneyphp\\'])->toBe('src/');

    expect($composer['autoload-dev']['psr-4'])->toHaveKey('Markommerce\\Money\\Moneyphp\\Tests\\')
        ->and($composer['autoload-dev']['psr-4']['Markommerce\\Money\\Moneyphp\\Tests\\'])->toBe('tests/');
});

it('is wired into the root composer.json require block', function (): void {
    $rootComposerPath = dirname(__DIR__, 4) . '/composer.json';

    expect(file_exists($rootComposerPath))->toBeTrue('root composer.json should exist');

    $rootComposer = json_decode((string) file_get_contents($rootComposerPath), true);

    expect($rootComposer['require'])->toHaveKey('markommerce/money-moneyphp')
        ->and($rootComposer['require']['markommerce/money-moneyphp'])->toBe('self.version');
});
