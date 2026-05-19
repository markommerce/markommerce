<?php

declare(strict_types=1);

use Markommerce\Scope\PgSql\Query\PgSqlScopedFieldRenderer;
use Markommerce\Scope\Query\ScopedFieldRendererInterface;

// Marko-specific configuration for this module.
// Name and version come from composer.json.

return [
    'bindings' => [
        ScopedFieldRendererInterface::class => PgSqlScopedFieldRenderer::class,
    ],
];
