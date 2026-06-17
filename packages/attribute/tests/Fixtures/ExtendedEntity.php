<?php

declare(strict_types=1);

namespace Markommerce\Attribute\Tests\Fixtures;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;

#[Table('fixture_extended')]
class ExtendedEntity extends Entity
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Column(length: 64, unique: true)]
    public string $sku = '';

    #[Column(length: 255)]
    public string $name = '';

    #[Column(name: 'created_at')]
    public string $createdAt = '';

    #[Column(name: 'updated_at', nullable: true)]
    public ?string $updatedAt = null;
}
