<?php

declare(strict_types=1);

namespace Markommerce\Testing\Tests\Fixture\Entity;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;

#[Table('fixture_parents')]
class ParentFixtureEntity extends Entity
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Column(length: 100)]
    public string $name = '';
}
