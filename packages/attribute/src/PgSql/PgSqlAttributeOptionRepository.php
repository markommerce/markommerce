<?php

declare(strict_types=1);

namespace Markommerce\Attribute\PgSql;

use Marko\Database\Repository\Repository;
use Markommerce\Attribute\Entity\AttributeOption;

/**
 * @extends Repository<AttributeOption>
 */
class PgSqlAttributeOptionRepository extends Repository
{
    protected const string ENTITY_CLASS = AttributeOption::class;
}
