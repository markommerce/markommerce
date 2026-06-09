<?php

declare(strict_types=1);

use Marko\Database\Repository\RepositoryInterface;
use Markommerce\Catalog\Contracts\CategoryTreeRepositoryInterface;
use Markommerce\Catalog\Entity\CategoryTree;
use Markommerce\Catalog\Exceptions\DefaultTreeMissingException;
use Markommerce\Catalog\Tests\Support\FakeCategoryTreeRepository;

it('interface extends Marko\Database\Repository\RepositoryInterface', function (): void {
    $reflection = new ReflectionClass(CategoryTreeRepositoryInterface::class);

    expect($reflection->isInterface())->toBeTrue();
    expect($reflection->implementsInterface(RepositoryInterface::class))->toBeTrue();
});

it(
    'interface declares findByCode and findDefault methods (custom methods only — base methods inherited)',
    function (): void {
        $reflection = new ReflectionClass(CategoryTreeRepositoryInterface::class);
    
        // Only own methods declared directly on this interface
    $ownMethods = array_map(
            fn (ReflectionMethod $m) => $m->getName(),
            array_filter(
                $reflection->getMethods(),
                fn (ReflectionMethod $m) => $m->getDeclaringClass()->getName() === CategoryTreeRepositoryInterface::class,
            ),
        );
    
        expect($ownMethods)->toContain('findByCode');
        expect($ownMethods)->toContain('findDefault');
        expect(count($ownMethods))->toBe(2);
    
        $findByCode = $reflection->getMethod('findByCode');
        $params = $findByCode->getParameters();
        expect($params)->toHaveCount(1);
        expect($params[0]->getName())->toBe('code');
        expect((string) $params[0]->getType())->toBe('string');
    
        $findDefault = $reflection->getMethod('findDefault');
        expect($findDefault->getParameters())->toHaveCount(0);
    }
);

it('fake stores a tree on save and returns it from find by id', function (): void {
    $repository = new FakeCategoryTreeRepository();
    $tree = new CategoryTree();
    $tree->code = 'main';
    $tree->name = 'Main Tree';

    $repository->save($tree);

    expect($tree->id)->not->toBeNull();
    assert($tree->id !== null);

    $found = $repository->find($tree->id);

    expect($found)->not->toBeNull();
    assert($found !== null);

    expect($found->id)->toBe($tree->id);
    expect($found->code)->toBe('main');
});

it('fake assigns an id when saving a tree with null id', function (): void {
    $repository = new FakeCategoryTreeRepository();
    $tree = new CategoryTree();
    $tree->code = 'second';
    $tree->name = 'Second Tree';

    expect($tree->id)->toBeNull();

    $repository->save($tree);

    expect($tree->id)->not->toBeNull();
    expect($tree->id)->toBeInt();
});

it('fake returns null from find when id does not exist', function (): void {
    $repository = new FakeCategoryTreeRepository();

    $result = $repository->find(999);

    expect($result)->toBeNull();
});

it('fake returns a tree by code when present', function (): void {
    $repository = new FakeCategoryTreeRepository();
    $tree = new CategoryTree();
    $tree->code = 'uk';
    $tree->name = 'UK Tree';

    $repository->save($tree);

    $found = $repository->findByCode('uk');

    expect($found)->not->toBeNull();
    assert($found !== null);

    expect($found->code)->toBe('uk');
});

it('fake returns null from findByCode when code does not exist', function (): void {
    $repository = new FakeCategoryTreeRepository();

    $result = $repository->findByCode('nonexistent');

    expect($result)->toBeNull();
});

it('fake findDefault returns the default tree when one exists', function (): void {
    $repository = new FakeCategoryTreeRepository();

    $tree = new CategoryTree();
    $tree->code = 'main';
    $tree->name = 'Main Tree';
    $tree->isDefault = true;

    $repository->save($tree);

    $default = $repository->findDefault();

    expect($default)->not->toBeNull();
    expect($default->code)->toBe('main');
    expect($default->isDefault)->toBeTrue();
});

it('fake findDefault throws DefaultTreeMissingException when no default exists', function (): void {
    $repository = new FakeCategoryTreeRepository();

    $tree = new CategoryTree();
    $tree->code = 'no-default';
    $tree->name = 'No Default Tree';
    $tree->isDefault = false;

    $repository->save($tree);

    expect(fn () => $repository->findDefault())->toThrow(DefaultTreeMissingException::class);
});

it('fake removes a tree on delete', function (): void {
    $repository = new FakeCategoryTreeRepository();
    $tree = new CategoryTree();
    $tree->code = 'to-delete';
    $tree->name = 'Tree To Delete';

    $repository->save($tree);

    assert($tree->id !== null);
    expect($repository->find($tree->id))->not->toBeNull();

    $repository->delete($tree);

    assert($tree->id !== null);
    expect($repository->find($tree->id))->toBeNull();
});

it('fake findAll returns all stored trees as an EntityCollection', function (): void {
    $repository = new FakeCategoryTreeRepository();

    $tree1 = new CategoryTree();
    $tree1->code = 'first';
    $tree1->name = 'First Tree';

    $tree2 = new CategoryTree();
    $tree2->code = 'second';
    $tree2->name = 'Second Tree';

    $repository->save($tree1);
    $repository->save($tree2);

    $all = $repository->findAll();

    expect($all)->toHaveCount(2);
    expect(array_any($all->toArray(), fn ($t) => $t->code === 'first'))->toBeTrue();
    expect(array_any($all->toArray(), fn ($t) => $t->code === 'second'))->toBeTrue();
});
