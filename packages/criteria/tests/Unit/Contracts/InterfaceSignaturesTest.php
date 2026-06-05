<?php

declare(strict_types=1);

use Markommerce\Criteria\Contracts\CursorValueExtractorInterface;
use Markommerce\Criteria\Contracts\PaginationStrategyInterface;
use Markommerce\Criteria\Contracts\RandomAccessPageInterface;
use Markommerce\Criteria\Contracts\RowCounterInterface;
use Marko\Database\Repository\RepositoryQueryBuilder;
use Markommerce\Criteria\Page\Page;
use Markommerce\Criteria\Page\PageRequest;
use Markommerce\Criteria\Sort\Sort;

it('the strategy and counter interfaces declare the documented method signatures', function (): void {
    // PaginationStrategyInterface::paginate signature
    $strategyRef = new ReflectionMethod(PaginationStrategyInterface::class, 'paginate');
    $params = $strategyRef->getParameters();

    expect($params)->toHaveCount(3);

    $queryType = $params[0]->getType();
    assert($queryType instanceof ReflectionNamedType);
    expect($params[0]->getName())->toBe('query')
        ->and($queryType->getName())->toBe(RepositoryQueryBuilder::class);

    $pageRequestType = $params[1]->getType();
    assert($pageRequestType instanceof ReflectionNamedType);
    expect($params[1]->getName())->toBe('pageRequest')
        ->and($pageRequestType->getName())->toBe(PageRequest::class);

    expect($params[2]->getName())->toBe('cursorValueExtractor')
        ->and($params[2]->isOptional())->toBeTrue()
        ->and($params[2]->allowsNull())->toBeTrue();

    $strategyReturnType = $strategyRef->getReturnType();
    assert($strategyReturnType instanceof ReflectionNamedType);
    expect($strategyReturnType->getName())->toBe(Page::class);

    // RowCounterInterface::count signature
    $counterRef = new ReflectionMethod(RowCounterInterface::class, 'count');
    $counterParams = $counterRef->getParameters();

    expect($counterParams)->toHaveCount(1);

    $counterQueryType = $counterParams[0]->getType();
    assert($counterQueryType instanceof ReflectionNamedType);
    expect($counterParams[0]->getName())->toBe('query')
        ->and($counterQueryType->getName())->toBe(RepositoryQueryBuilder::class);

    $counterReturnType = $counterRef->getReturnType();
    assert($counterReturnType instanceof ReflectionNamedType);
    expect($counterReturnType->getName())->toBe('int');

    // RandomAccessPageInterface methods exist
    $randomRef = new ReflectionClass(RandomAccessPageInterface::class);
    expect($randomRef->hasMethod('currentPage'))->toBeTrue()
        ->and($randomRef->hasMethod('totalPages'))->toBeTrue()
        ->and($randomRef->hasMethod('totalItems'))->toBeTrue()
        ->and($randomRef->hasMethod('positionForPage'))->toBeTrue();

    $positionForPageRef = new ReflectionMethod(RandomAccessPageInterface::class, 'positionForPage');
    $positionParams = $positionForPageRef->getParameters();
    expect($positionParams)->toHaveCount(1);

    $positionParamType = $positionParams[0]->getType();
    assert($positionParamType instanceof ReflectionNamedType);
    expect($positionParams[0]->getName())->toBe('page')
        ->and($positionParamType->getName())->toBe('int');

    $positionReturnType = $positionForPageRef->getReturnType();
    assert($positionReturnType instanceof ReflectionNamedType);
    expect($positionReturnType->getName())->toBe('string');

    // CursorValueExtractorInterface::extract signature
    $extractorRef = new ReflectionMethod(CursorValueExtractorInterface::class, 'extract');
    $extractorParams = $extractorRef->getParameters();

    expect($extractorParams)->toHaveCount(2);

    $entityType = $extractorParams[0]->getType();
    assert($entityType instanceof ReflectionNamedType);
    expect($extractorParams[0]->getName())->toBe('entity')
        ->and($entityType->getName())->toBe('object');

    $sortType = $extractorParams[1]->getType();
    assert($sortType instanceof ReflectionNamedType);
    expect($extractorParams[1]->getName())->toBe('sort')
        ->and($sortType->getName())->toBe(Sort::class);

    $extractorReturnType = $extractorRef->getReturnType();
    assert($extractorReturnType instanceof ReflectionNamedType);
    expect($extractorReturnType->getName())->toBe('array');
});
