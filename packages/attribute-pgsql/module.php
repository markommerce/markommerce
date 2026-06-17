<?php

declare(strict_types=1);

use Markommerce\Attribute\Contracts\AttributeDefinitionRepositoryInterface;
use Markommerce\Attribute\PgSql\PgSqlAttributeDefinitionRepository;

return [
    'bindings' => [
        AttributeDefinitionRepositoryInterface::class => PgSqlAttributeDefinitionRepository::class,
    ],
];
