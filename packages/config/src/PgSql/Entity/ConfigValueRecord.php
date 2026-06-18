<?php

declare(strict_types=1);

namespace Markommerce\Config\PgSql\Entity;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;

// NOTE: The GIN index on the `value` JSONB column used in production
// cannot be expressed with Marko's #[Index] attribute (no USING GIN support).
// It is intentionally omitted here. The existing ConfigValuesTableEmitter
// and manual migration path remain in place for production deployments.
//
// NOTE: The `updated_at` column has no DEFAULT in the entity declaration
// because Marko cannot emit DEFAULT NOW(). The runtime storage layer
// (PgsqlConfigStorage) always supplies updated_at = NOW() at write time.
// The `version` DEFAULT 0 is also storage-supplied.
#[Table('config_values')]
class ConfigValueRecord extends Entity
{
    // config_key is the primary key (single-column, VARCHAR 255)
    #[Column(name: 'config_key', primaryKey: true, length: 255)]
    public string $configKey = '';

    #[Column(type: 'jsonb', nullable: true)]
    public ?string $value = null;

    #[Column(type: 'integer', nullable: false)]
    public int $version = 0;

    // PHP default null prevents EntityMetadataFactory from picking up '' as a DB DEFAULT.
    // The storage layer (PgsqlConfigStorage) always supplies updated_at = NOW() at write time.
    // The column remains NOT NULL in the schema (enforced by nullable: false in the attribute).
    #[Column(name: 'updated_at', type: 'timestamptz', nullable: false)]
    public ?string $updatedAt = null;
}
