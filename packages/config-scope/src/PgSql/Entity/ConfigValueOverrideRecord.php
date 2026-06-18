<?php

declare(strict_types=1);

namespace Markommerce\ConfigScope\PgSql\Entity;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Index;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;

// NOTE: The overrides table has a logical composite PK on (config_key, signature).
// Marko does not support composite primary keys, so a surrogate autoincrement `id`
// column is used as the entity PK instead.
// The UNIQUE index on (config_key, signature) is REQUIRED because PgsqlScopedConfigStorage
// upserts via ON CONFLICT (config_key, signature) — that conflict target needs a
// matching unique constraint in the database.
//
// NOTE: `updated_at` has no DEFAULT in the entity because Marko cannot emit DEFAULT NOW().
// PgsqlScopedConfigStorage always supplies updated_at = NOW() at write time.
// The `version` DEFAULT 0 is also storage-supplied.
#[Table('config_value_overrides')]
#[Index(name: 'uniq_config_value_overrides_key_signature', columns: ['config_key', 'signature'], unique: true)]
class ConfigValueOverrideRecord extends Entity
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Column(name: 'config_key', length: 255, nullable: false)]
    public string $configKey = '';

    #[Column(length: 255, nullable: false)]
    public string $signature = '';

    #[Column(type: 'jsonb', nullable: false)]
    public string $value = '';

    #[Column(type: 'integer', nullable: false)]
    public int $version = 0;

    #[Column(name: 'updated_at', type: 'timestamptz', nullable: false)]
    public string $updatedAt = '';
}
