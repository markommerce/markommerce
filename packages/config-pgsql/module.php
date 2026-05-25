<?php

declare(strict_types=1);

use Marko\Core\Container\ContainerInterface;
use Marko\Database\Connection\ConnectionInterface;
use Markommerce\Config\Contracts\ConfigStorageInterface;
use Markommerce\Config\PgSql\PgsqlConfigStorage;

// Marko-specific configuration for this module.
// Name and version come from composer.json.

return [
    'bindings' => [
        ConfigStorageInterface::class => static function (ContainerInterface $container): PgsqlConfigStorage {
            return new PgsqlConfigStorage($container->get(ConnectionInterface::class));
        },
    ],
];
