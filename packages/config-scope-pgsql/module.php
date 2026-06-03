<?php

declare(strict_types=1);

use Marko\Core\Container\ContainerInterface;
use Marko\Database\Connection\ConnectionInterface;
use Markommerce\ConfigScope\Contracts\ScopedConfigStorageInterface;
use Markommerce\ConfigScope\PgSql\PgsqlScopedConfigStorage;

// Marko-specific configuration for this module.
// Name and version come from composer.json.

return [
    'bindings' => [
        ScopedConfigStorageInterface::class => static function (ContainerInterface $container): PgsqlScopedConfigStorage {
            return new PgsqlScopedConfigStorage($container->get(ConnectionInterface::class));
        },
    ],
];
