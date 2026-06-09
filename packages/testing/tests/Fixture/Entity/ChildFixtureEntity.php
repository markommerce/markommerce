<?php

declare(strict_types=1);

namespace Markommerce\Testing\Tests\Fixture\Entity;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;

#[Table('fixture_children')]
class ChildFixtureEntity extends Entity
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Column(name: 'parent_id', references: 'fixture_parents.id', onDelete: 'CASCADE')]
    public ?int $parentId = null;

    #[Column(length: 200)]
    public string $label = '';
}
