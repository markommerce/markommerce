<?php

declare(strict_types=1);

namespace Markommerce\Attribute\Tests\Contract;

use Markommerce\Attribute\Contracts\AttributeDefinitionRepositoryInterface;
use Markommerce\Attribute\Entity\AttributeDefinition;
use Markommerce\Attribute\Entity\AttributeOption;

/**
 * Shared repository contract suite.
 *
 * Call this function from any Pest test file, passing a factory callable that
 * returns a fresh AttributeDefinitionRepositoryInterface for each test case.
 *
 * Task 012 runs this against the in-memory fake:
 *
 *   attributeDefinitionRepositoryContract(fn () => new FakeAttributeDefinitionRepository());
 *
 * Task 014 runs the SAME suite against the PgSql driver by passing its own factory:
 *
 *   attributeDefinitionRepositoryContract(fn () => new PgSqlAttributeDefinitionRepository(...));
 *
 * Autoloaded under Markommerce\Attribute\Tests\ (registered in the root composer.json
 * autoload-dev), so it is importable from any package's test suite.
 */
function attributeDefinitionRepositoryContract(callable $makeRepo): void
{
    it('persists a definition and retrieves it by id', function () use ($makeRepo): void {
        /** @var AttributeDefinitionRepositoryInterface $repo */
        $repo = $makeRepo();

        $definition = new AttributeDefinition();
        $definition->code = 'color';
        $definition->entityType = 'product';
        $definition->type = 'select';
        $definition->label = 'Color';

        $repo->save($definition);

        expect($definition->id)->not->toBeNull();

        $found = $repo->find($definition->id);

        expect($found)->not->toBeNull()
            ->and($found->code)->toBe('color')
            ->and($found->entityType)->toBe('product');
    });

    it('retrieves a definition by entity type and code', function () use ($makeRepo): void {
        /** @var AttributeDefinitionRepositoryInterface $repo */
        $repo = $makeRepo();

        $definition = new AttributeDefinition();
        $definition->code = 'size';
        $definition->entityType = 'product';
        $definition->type = 'select';
        $definition->label = 'Size';

        $repo->save($definition);

        $found = $repo->findByCode('product', 'size');

        expect($found)->not->toBeNull()
            ->and($found->code)->toBe('size')
            ->and($found->entityType)->toBe('product');
    });

    it('returns null from findByCode when no definition matches', function () use ($makeRepo): void {
        /** @var AttributeDefinitionRepositoryInterface $repo */
        $repo = $makeRepo();

        expect($repo->findByCode('product', 'nonexistent'))->toBeNull();
    });

    it('persists and retrieves options for a definition', function () use ($makeRepo): void {
        /** @var AttributeDefinitionRepositoryInterface $repo */
        $repo = $makeRepo();

        $definition = new AttributeDefinition();
        $definition->code = 'color';
        $definition->entityType = 'product';
        $definition->type = 'select';
        $definition->label = 'Color';

        $repo->save($definition);

        $option = new AttributeOption();
        $option->attributeId = $definition->id;
        $option->value = 'red';
        $option->label = 'Red';
        $option->position = 0;

        $repo->saveOption($option);

        $options = $repo->optionsFor($definition);

        expect($options)->toHaveCount(1)
            ->and($options[0]->value)->toBe('red');
    });

    it('saves multiple options and retrieves them all via optionsFor', function () use ($makeRepo): void {
        /** @var AttributeDefinitionRepositoryInterface $repo */
        $repo = $makeRepo();

        $definition = new AttributeDefinition();
        $definition->code = 'size';
        $definition->entityType = 'product';
        $definition->type = 'select';
        $definition->label = 'Size';

        $repo->save($definition);

        $optionSmall = new AttributeOption();
        $optionSmall->attributeId = $definition->id;
        $optionSmall->value = 'S';
        $optionSmall->label = 'Small';
        $optionSmall->position = 0;

        $optionLarge = new AttributeOption();
        $optionLarge->attributeId = $definition->id;
        $optionLarge->value = 'L';
        $optionLarge->label = 'Large';
        $optionLarge->position = 1;

        $repo->saveOption($optionSmall);
        $repo->saveOption($optionLarge);

        $options = $repo->optionsFor($definition);

        expect($options)->toHaveCount(2);
    });

    it('removes all options for a definition when deleteOptionsFor is called', function () use ($makeRepo): void {
        /** @var AttributeDefinitionRepositoryInterface $repo */
        $repo = $makeRepo();

        $definition = new AttributeDefinition();
        $definition->code = 'color';
        $definition->entityType = 'product';
        $definition->type = 'select';
        $definition->label = 'Color';

        $repo->save($definition);

        $option = new AttributeOption();
        $option->attributeId = $definition->id;
        $option->value = 'blue';
        $option->label = 'Blue';

        $repo->saveOption($option);
        $repo->deleteOptionsFor($definition);

        expect($repo->optionsFor($definition))->toHaveCount(0);
    });

    it('cascades option deletion when a definition is deleted', function () use ($makeRepo): void {
        /** @var AttributeDefinitionRepositoryInterface $repo */
        $repo = $makeRepo();

        $definition = new AttributeDefinition();
        $definition->code = 'color';
        $definition->entityType = 'product';
        $definition->type = 'select';
        $definition->label = 'Color';

        $repo->save($definition);
        $defId = $definition->id;

        $option = new AttributeOption();
        $option->attributeId = $defId;
        $option->value = 'green';
        $option->label = 'Green';

        $repo->saveOption($option);
        $repo->delete($definition);

        expect($repo->find($defId))->toBeNull();

        // No orphaned options remain after cascade delete
        $ghost = new AttributeDefinition();
        $ghost->id = $defId;

        expect($repo->optionsFor($ghost))->toHaveCount(0);
    });
}
