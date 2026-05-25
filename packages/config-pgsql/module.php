<?php

declare(strict_types=1);

use Markommerce\Config\Contracts\ConfigStorageInterface;
use Markommerce\Config\PgSql\PgsqlConfigStorage;

// Marko-specific configuration for this module.
// Name and version come from composer.json.

return [
    'bindings' => [
        ConfigStorageInterface::class => PgsqlConfigStorage::class,
    ],
];
