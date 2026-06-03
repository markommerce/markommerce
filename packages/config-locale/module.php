<?php

declare(strict_types=1);

/**
 * config-locale module manifest.
 *
 * Placeholder — no fields registered today. This bridge reserves the
 * ScopedFieldRegistry hook for future locale-scoped config resolution.
 * See FEATURES.md tier rows for the planned end state.
 */

use Markommerce\Scope\Metadata\ScopedFieldRegistry;

return [
    'require' => [
        'markommerce/config-scope' => '*',
        'markommerce/locale'       => '*',
    ],
    'boot' => function (ScopedFieldRegistry $scopedFieldRegistry): void {
        // Placeholder: no scoped fields registered yet.
    },
];
