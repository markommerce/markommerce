<?php

declare(strict_types=1);

namespace Markommerce\MoneyIntl\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class MoneyFormattingException extends MarkoException
{
    public static function forMissingIntlExtension(): self
    {
        return new self(
            message: 'The PHP intl extension is required by markommerce/money-intl but is not loaded.',
            context: 'MoneyFormatter relies on NumberFormatter (ext-intl) to produce locale-aware currency strings.',
            suggestion: 'Enable ext-intl in your php.ini (uncomment or add "extension=intl") and restart PHP.',
        );
    }

    public static function forInvalidLocale(string $locale): self
    {
        return new self(
            message: "Could not create a NumberFormatter for locale '$locale'.",
            context: 'NumberFormatter construction failed — the locale string may be malformed or unsupported by the installed ICU library.',
            suggestion: "Use a valid BCP-47/ICU locale tag such as 'en_US', 'de_DE', or 'fr_FR'.",
        );
    }
}
