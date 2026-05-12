<?php

declare(strict_types=1);

use Markommerce\Money\CurrencyConfigInterface;
use Markommerce\Money\MoneyFactoryInterface;
use Markommerce\Money\Moneyphp\MoneyFactory;

class FakeCurrencyConfig implements CurrencyConfigInterface
{
    public function __construct(public string $default = 'USD') {}

    public function getDefault(): string
    {
        return $this->default;
    }
}

it('implements MoneyFactoryInterface in MoneyFactory', function (): void {
    expect(class_exists(MoneyFactory::class))->toBeTrue();

    $reflection = new ReflectionClass(MoneyFactory::class);
    expect($reflection->implementsInterface(MoneyFactoryInterface::class))->toBeTrue();
    expect($reflection->isFinal())->toBeFalse();
});

it('creates a Money with the explicit currency when one is passed to MoneyFactory create', function (): void {
    $factory = new MoneyFactory(new FakeCurrencyConfig('USD'));
    $money = $factory->create(500, 'EUR');

    expect($money->currency())->toBe('EUR');
});

it('creates a Money with the CurrencyConfig default currency when no currency is passed to MoneyFactory create', function (): void {
    $factory = new MoneyFactory(new FakeCurrencyConfig('GBP'));
    $money = $factory->create(100);

    expect($money->currency())->toBe('GBP');
});

it('calls CurrencyConfig getDefault on every create invocation rather than caching once at construction (assert by mutating FakeCurrencyConfig between two create calls and confirming both currencies match the mutated value)', function (): void {
    $fakeCurrencyConfig = new FakeCurrencyConfig('USD');
    $factory = new MoneyFactory($fakeCurrencyConfig);

    $money1 = $factory->create(100);
    expect($money1->currency())->toBe('USD');

    $fakeCurrencyConfig->default = 'EUR';

    $money2 = $factory->create(200);
    expect($money2->currency())->toBe('EUR');
});

it('uses the integer amount verbatim on the returned Money', function (): void {
    $factory = new MoneyFactory(new FakeCurrencyConfig('USD'));
    $money = $factory->create(4250);

    expect($money->amount())->toBe(4250);
});
