<?php

declare(strict_types=1);

use Markommerce\Money\Currency;
use Markommerce\Money\Money;
use Markommerce\MoneyIntl\Exceptions\MoneyFormattingException;
use Markommerce\MoneyIntl\MoneyFormatter;
use Markommerce\Scope\Axis\ScopeAxis;
use Markommerce\Scope\Context\ScopeContext;
use Markommerce\Scope\Exceptions\UnknownAxisException;
use Markommerce\Scope\Hierarchy\ScopeHierarchy;
use Markommerce\Scope\Registry\ScopeRegistryInterface;

// ─── Helpers ─────────────────────────────────────────────────────────────────

function usdCurrency(): Currency
{
    return new Currency(code: 'USD', scale: 2, symbol: '$', name: 'US Dollar');
}

function eurCurrency(): Currency
{
    return new Currency(code: 'EUR', scale: 2, symbol: '€', name: 'Euro');
}

function jpyCurrency(): Currency
{
    return new Currency(code: 'JPY', scale: 0, symbol: '¥', name: 'Japanese Yen');
}

/**
 * Returns a ScopeContext whose get('locale') returns the given value.
 */
function makeScopeContextStub(?string $locale): ScopeContext
{
    $registry = new class () implements ScopeRegistryInterface
    {
        public function hasAxis(string $name): bool
        {
            return false;
        }

        public function getAxis(string $name): ScopeAxis
        {
            throw UnknownAxisException::forAxis($name);
        }

        /** @return list<string> */
        public function listAxes(): array
        {
            return [];
        }

        public function getHierarchy(string $axisName): ScopeHierarchy
        {
            throw UnknownAxisException::forAxis($axisName);
        }
    };

    return new class ($locale, $registry) extends ScopeContext
    {
        public function __construct(
            private readonly ?string $activeLocale,
            ScopeRegistryInterface $registry,
        ) {
            parent::__construct($registry);
        }

        public function get(string $axis): ?string
        {
            if ($axis === 'locale') {
                return $this->activeLocale;
            }

            return null;
        }
    };
}

/**
 * Builds a MoneyFormatter with the given active locale (null = no locale in context).
 */
function makeFormatter(?string $locale = null): MoneyFormatter
{
    return new MoneyFormatter(makeScopeContextStub($locale));
}

/**
 * Builds a MoneyFormatter with a fake intl-availability check.
 *
 * @throws MoneyFormattingException
 */
function makeFormatterWithIntlCheck(bool $intlAvailable, ?string $locale = null): MoneyFormatter
{
    return new MoneyFormatter(
        scopeContext: makeScopeContextStub($locale),
        intlChecker: static fn (): bool => $intlAvailable,
    );
}

// ─── Tests ────────────────────────────────────────────────────────────────────

it('formats money for an explicitly provided locale', function (): void {
    $formatter = makeFormatter();
    $money = Money::of('1234.56', usdCurrency());

    $result = $formatter->formatFor($money, 'en_US');

    expect($result)->toBeString()
        ->and($result)->not->toBeEmpty()
        ->and($result)->toContain('1,234.56')
        ->and($result)->toContain('$');
});

it('formats money using the active locale from scope context', function (): void {
    $formatter = makeFormatter('en_US');
    $money = Money::of('9.99', usdCurrency());

    $result = $formatter->format($money);

    expect($result)->toBeString()
        ->and($result)->toContain('9.99')
        ->and($result)->toContain('$');
});

it('falls back to the default locale when no active locale is set', function (): void {
    $formatter = makeFormatter(null);
    $money = Money::of('10.00', usdCurrency());

    $result = $formatter->format($money);

    // When no locale is set, MoneyFormatter falls back to DEFAULT_LOCALE ('en')
    expect($result)->toBeString()
        ->and($result)->not->toBeEmpty()
        ->and($result)->toContain('10.00')
        ->and($result)->toContain('$');
});

it('renders the currency symbol and grouping according to the locale', function (): void {
    $formatter = makeFormatter('en_US');
    $money = Money::of('2500.00', eurCurrency());

    $result = $formatter->formatFor($money, 'en_US');

    expect($result)->toContain('€')
        ->and($result)->toContain('2,500.00');
});

it('formats a zero decimal currency like JPY without fraction digits', function (): void {
    $formatter = makeFormatter('en_US');
    $money = Money::of('1500', jpyCurrency());

    $result = $formatter->formatFor($money, 'en_US');

    // JPY has scale 0; NumberFormatter should not add decimal places
    expect($result)->toContain('¥')
        ->and($result)->not->toContain('.')
        ->and($result)->toContain('1,500');
});

it('throws MoneyFormattingException when the intl extension is unavailable', function (): void {
    expect(static fn () => makeFormatterWithIntlCheck(false))
        ->toThrow(MoneyFormattingException::class);
});
