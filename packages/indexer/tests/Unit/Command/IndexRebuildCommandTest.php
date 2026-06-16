<?php

declare(strict_types=1);

use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Markommerce\Indexer\Command\IndexRebuildCommand;
use Markommerce\Indexer\Contracts\IndexerInterface;
use Markommerce\Indexer\Registry\IndexerRegistry;

function makeIndexRebuildOutput(): Output
{
    $stream = fopen('php://memory', 'rw');

    return new Output($stream);
}

function readOutput(Output $output): string
{
    $reflection = new ReflectionProperty(Output::class, 'stream');
    $stream = $reflection->getValue($output);
    rewind($stream);

    return stream_get_contents($stream);
}

function makeCountingIndexer(int $count): IndexerInterface
{
    return new class ($count) implements IndexerInterface
    {
        public function __construct(private int $count) {}

        public function reindex(array $ids): int
        {
            return count($ids);
        }

        public function reindexOne(int $id): int
        {
            return 1;
        }

        public function rebuildAll(int $chunkSize = 500): int
        {
            return $this->count;
        }
    };
}

it('rebuilds only the named index when a name is given', function (): void {
    $registry = new IndexerRegistry();
    $registry->register('product_flat', makeCountingIndexer(10));
    $registry->register('price_index', makeCountingIndexer(20));

    $command = new IndexRebuildCommand($registry);
    $output = makeIndexRebuildOutput();

    // Argument at index 0 after the command name
    $input = new Input(['marko', 'index:rebuild', 'product_flat']);
    $exitCode = $command->execute($input, $output);

    expect($exitCode)->toBe(0);

    $text = readOutput($output);
    expect($text)->toContain('product_flat');
    expect($text)->toContain('10');
    expect($text)->not->toContain('price_index');
});

it('rebuilds all registered indexes when no name is given', function (): void {
    $registry = new IndexerRegistry();
    $registry->register('product_flat', makeCountingIndexer(10));
    $registry->register('price_index', makeCountingIndexer(20));

    $command = new IndexRebuildCommand($registry);
    $output = makeIndexRebuildOutput();

    $input = new Input(['marko', 'index:rebuild']);
    $exitCode = $command->execute($input, $output);

    expect($exitCode)->toBe(0);

    $text = readOutput($output);
    expect($text)->toContain('product_flat');
    expect($text)->toContain('price_index');
    expect($text)->toContain('30'); // total
});
