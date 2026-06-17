<?php

declare(strict_types=1);

use Markommerce\Layout\Contracts\SourceInterface;
use Markommerce\Layout\Source\Source;

it('builds a route source with a name and cast type', function (): void {
    $source = Source::route('id', 'int');
    expect($source->name)->toBe('id');
    expect($source->as)->toBe('int');
});

it('builds a query source with a name, default and cast type', function (): void {
    $source = Source::query('page', 1, 'int');
    expect($source->name)->toBe('page');
    expect($source->default)->toBe(1);
    expect($source->as)->toBe('int');
});

it('builds a context source with a token class and optional dot path', function (): void {
    $source = Source::context('SomeToken::class', 'some.path');
    expect($source->token)->toBe('SomeToken::class');
    expect($source->path)->toBe('some.path');

    $sourceWithoutPath = Source::context('SomeToken::class');
    expect($sourceWithoutPath->path)->toBeNull();
});

it('builds an iterated source with an iteration token and optional dot path', function (): void {
    $source = Source::iterated('IterationToken::class', 'item.name');
    expect($source->token)->toBe('IterationToken::class');
    expect($source->path)->toBe('item.name');

    $sourceWithoutPath = Source::iterated('IterationToken::class');
    expect($sourceWithoutPath->path)->toBeNull();
});

it('builds a parent-data source with a key and cast type', function (): void {
    $source = Source::parentData('sku', 'string');
    expect($source->key)->toBe('sku');
    expect($source->as)->toBe('string');
});

it('builds a service source with a class name', function (): void {
    $source = Source::service('SomeService::class');
    expect($source->class)->toBe('SomeService::class');
});

it('allows an array cast on a query source', function (): void {
    $source = Source::query('filter', [], 'array');
    expect($source->name)->toBe('filter');
    expect($source->default)->toBe([]);
    expect($source->as)->toBe('array');
});

it('rejects an unknown cast keyword on a route source', function (): void {
    expect(fn () => Source::route('id', 'float'))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects an unknown cast keyword on a query source', function (): void {
    expect(fn () => Source::query('page', null, 'float'))
        ->toThrow(InvalidArgumentException::class);
});

it('marks every source object with the common source interface', function (): void {
    expect(Source::route('id'))->toBeInstanceOf(SourceInterface::class);
    expect(Source::query('page'))->toBeInstanceOf(SourceInterface::class);
    expect(Source::context('Token::class'))->toBeInstanceOf(SourceInterface::class);
    expect(Source::iterated('Token::class'))->toBeInstanceOf(SourceInterface::class);
    expect(Source::parentData('sku'))->toBeInstanceOf(SourceInterface::class);
    expect(Source::service('MyService::class'))->toBeInstanceOf(SourceInterface::class);
});

it('treats a non-source prop value as a literal', function (): void {
    expect('hello')->not->toBeInstanceOf(SourceInterface::class);
    expect(42)->not->toBeInstanceOf(SourceInterface::class);
    expect(['key' => 'value'])->not->toBeInstanceOf(SourceInterface::class);
    expect(true)->not->toBeInstanceOf(SourceInterface::class);
});
