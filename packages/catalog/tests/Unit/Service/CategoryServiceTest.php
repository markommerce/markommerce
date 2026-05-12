<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Tests\Unit\Service;

use Markommerce\Catalog\Entity\Category;
use Markommerce\Catalog\Event\CategoryCreated;
use Markommerce\Catalog\Event\CategoryDeleted;
use Markommerce\Catalog\Event\CategoryUpdated;
use Markommerce\Catalog\Exception\CategoryNotFoundException;
use Markommerce\Catalog\Exception\InvalidCategoryDataException;
use Markommerce\Catalog\Service\CategoryService;
use Markommerce\Catalog\Tests\Support\FakeCategoryRepository;
use Markommerce\Catalog\Tests\Support\RecordingEventDispatcher;

it('creates a category with a valid name and returns the persisted entity', function (): void {
    $repo = new FakeCategoryRepository();
    $service = new CategoryService($repo);

    $category = $service->create('Electronics');

    expect($category)->toBeInstanceOf(Category::class)
        ->and($category->name)->toBe('Electronics')
        ->and($repo->saved)->toBe($category);
});

it('rejects a category with an empty name by throwing InvalidCategoryDataException', function (): void {
    $repo = new FakeCategoryRepository();
    $service = new CategoryService($repo);

    expect(fn () => $service->create(''))->toThrow(InvalidCategoryDataException::class);
});

it('dispatches CategoryCreated after a successful create when a dispatcher is bound', function (): void {
    $repo = new FakeCategoryRepository();
    $dispatcher = new RecordingEventDispatcher();
    $service = new CategoryService($repo, $dispatcher);

    $category = $service->create('Books');

    expect($dispatcher->events)->toHaveCount(1)
        ->and($dispatcher->events[0])->toBeInstanceOf(CategoryCreated::class);

    assert($dispatcher->events[0] instanceof CategoryCreated);
    expect($dispatcher->events[0]->category)->toBe($category);
});

it('returns the category from get when it exists', function (): void {
    $repo = new FakeCategoryRepository();
    $service = new CategoryService($repo);

    $category = new Category();
    $category->id = 5;
    $category->name = 'Sports';
    $repo->categories[] = $category;

    $found = $service->get(5);

    expect($found)->toBe($category);
});

it('returns null from get when the category does not exist', function (): void {
    $repo = new FakeCategoryRepository();
    $service = new CategoryService($repo);

    $found = $service->get(99);

    expect($found)->toBeNull();
});

it('updates an existing category and dispatches CategoryUpdated', function (): void {
    $repo = new FakeCategoryRepository();
    $dispatcher = new RecordingEventDispatcher();
    $service = new CategoryService($repo, $dispatcher);

    $category = new Category();
    $category->id = 1;
    $category->name = 'Old Name';
    $repo->categories[] = $category;

    $category->name = 'New Name';
    $returned = $service->update($category);

    expect($returned)->toBe($category)
        ->and($repo->saved)->toBe($category)
        ->and($dispatcher->events)->toHaveCount(1)
        ->and($dispatcher->events[0])->toBeInstanceOf(CategoryUpdated::class);

    assert($dispatcher->events[0] instanceof CategoryUpdated);
    expect($dispatcher->events[0]->category)->toBe($category);
});

it('deletes an existing category and dispatches CategoryDeleted', function (): void {
    $repo = new FakeCategoryRepository();
    $dispatcher = new RecordingEventDispatcher();
    $service = new CategoryService($repo, $dispatcher);

    $category = new Category();
    $category->id = 3;
    $category->name = 'Toys';
    $repo->categories[] = $category;

    $service->delete(3);

    expect($repo->deleted)->toBe($category)
        ->and($repo->categories)->toBeEmpty()
        ->and($dispatcher->events)->toHaveCount(1)
        ->and($dispatcher->events[0])->toBeInstanceOf(CategoryDeleted::class);

    assert($dispatcher->events[0] instanceof CategoryDeleted);
    expect($dispatcher->events[0]->categoryId)->toBe(3);
});

it('throws CategoryNotFoundException from delete when the category does not exist', function (): void {
    $repo = new FakeCategoryRepository();
    $service = new CategoryService($repo);

    expect(fn () => $service->delete(42))->toThrow(CategoryNotFoundException::class);
});

it('lists all categories via the repository', function (): void {
    $repo = new FakeCategoryRepository();
    $service = new CategoryService($repo);

    $cat1 = new Category();
    $cat1->id = 1;
    $cat1->name = 'Alpha';

    $cat2 = new Category();
    $cat2->id = 2;
    $cat2->name = 'Beta';

    $repo->categories = [$cat1, $cat2];

    $list = $service->list();

    expect($list)->toHaveCount(2)
        ->and($list[0])->toBe($cat1)
        ->and($list[1])->toBe($cat2);
});

it('works without an event dispatcher by skipping dispatch calls silently', function (): void {
    $repo = new FakeCategoryRepository();
    $service = new CategoryService($repo); // no dispatcher

    $category = $service->create('No Dispatcher');

    expect($category)->toBeInstanceOf(Category::class)
        ->and($category->name)->toBe('No Dispatcher');

    $category->name = 'Updated';
    $service->update($category);

    assert($category->id !== null);
    $service->delete($category->id);
});
