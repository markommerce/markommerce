<?php

declare(strict_types=1);

use Markommerce\Attribute\Tests\Support\FakeAttributeDefinitionRepository;

require_once __DIR__ . '/../../Contract/AttributeDefinitionRepositoryContractTest.php';

use function Markommerce\Attribute\Tests\Contract\attributeDefinitionRepositoryContract;

// Run the shared contract suite against the in-memory fake.
// Task 014 will call attributeDefinitionRepositoryContract() from its own test
// file, passing the PgSql repository factory instead.
attributeDefinitionRepositoryContract(fn () => new FakeAttributeDefinitionRepository());
