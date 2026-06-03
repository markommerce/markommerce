<?php

declare(strict_types=1);

namespace Markommerce\MoneyIntl;

use Closure;
use Markommerce\Money\Money;
use Markommerce\MoneyIntl\Exceptions\MoneyFormattingException;
use Markommerce\Scope\Context\ScopeContext;
use NumberFormatter;

class MoneyFormatter
{
    /**
     * Fallback locale used when no active locale is found in the scope context.
     */
    public const string DEFAULT_LOCALE = 'en';

    /**
     * @param (Closure(): bool)|null $intlChecker Returns true when ext-intl is available.
     *                                             Pass null to use the default extension_loaded() check.
     *
     * @throws MoneyFormattingException
     */
    public function __construct(
        private readonly ScopeContext $scopeContext,
        private readonly ?Closure $intlChecker = null,
    ) {
        $checker = $this->intlChecker ?? static fn (): bool => extension_loaded('intl');

        if (!($checker)()) {
            throw MoneyFormattingException::forMissingIntlExtension();
        }
    }

    /**
     * Formats a Money value for the explicitly specified locale.
     *
     * @throws MoneyFormattingException
     */
    public function formatFor(
        Money $money,
        string $locale,
    ): string {
        $formatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);

        // The canonical amount is Money->amount() (decimal string). We cast to float only
        // at this presentation boundary because NumberFormatter::formatCurrency() requires
        // a float. Amounts stored in decimal(20,4) are ≤ 4 dp, well within float's safe
        // integer range for realistic prices. This float is never persisted or used for
        // arithmetic — the string from Money->amount() remains the source of truth.
        $amount = (float) $money->amount();

        $result = $formatter->formatCurrency($amount, $money->currency()->code);

        if ($result === false) {
            throw MoneyFormattingException::forInvalidLocale($locale);
        }

        return $result;
    }

    /**
     * Formats a Money value using the active locale from the scope context,
     * falling back to {@see self::DEFAULT_LOCALE} when no locale is active.
     *
     * @throws MoneyFormattingException
     */
    public function format(Money $money): string
    {
        $locale = $this->scopeContext->get('locale') ?? self::DEFAULT_LOCALE;

        return $this->formatFor($money, $locale);
    }
}
