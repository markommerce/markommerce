<?php

declare(strict_types=1);

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Markommerce\CatalogPriceIndex\Command\RebuildPriceIndexCommand;
use Markommerce\CatalogPriceIndex\Contracts\PriceIndexerInterface;

// ─── Fake ─────────────────────────────────────────────────────────────────────

class FakePriceIndexer implements PriceIndexerInterface
{
    public int $rebuildCallCount = 0;

    public ?int $lastChunkSize = null;

    public int $rebuildReturn = 0;

    public function reindexProducts(array $ids): int
    {
        return 0;
    }

    public function reindexProduct(int $id): int
    {
        return 0;
    }

    public function rebuildAll(int $chunkSize = 500): int
    {
        $this->rebuildCallCount++;
        $this->lastChunkSize = $chunkSize;

        return $this->rebuildReturn;
    }
}

// ─── Helper ───────────────────────────────────────────────────────────────────

function captureRebuildOutput(callable $callback): string
{
    $stream = fopen('php://memory', 'r+');
    assert($stream !== false);
    $output = new Output($stream);
    $callback($output);
    rewind($stream);
    $content = stream_get_contents($stream);
    fclose($stream);

    return $content !== false ? $content : '';
}

// ─── Tests ────────────────────────────────────────────────────────────────────

it('is registered under the name catalog price index rebuild', function (): void {
    $reflection = new ReflectionClass(RebuildPriceIndexCommand::class);
    $attributes = $reflection->getAttributes(Command::class);

    expect($attributes)->toHaveCount(1);
    expect($attributes[0]->newInstance()->name)->toBe('catalog:price-index:rebuild');
    expect(RebuildPriceIndexCommand::class)->toImplement(CommandInterface::class);
});

it('rebuilds the whole index when invoked', function (): void {
    $indexer = new FakePriceIndexer();
    $command = new RebuildPriceIndexCommand($indexer);
    $input   = new Input(['marko', 'catalog:price-index:rebuild']);
    $output  = new Output(fopen('php://memory', 'r+'));

    $command->execute($input, $output);

    expect($indexer->rebuildCallCount)->toBe(1);
});

it('passes the chunk size option through to the indexer', function (): void {
    $indexer = new FakePriceIndexer();
    $command = new RebuildPriceIndexCommand($indexer);
    $input   = new Input(['marko', 'catalog:price-index:rebuild', '--chunk=200']);
    $output  = new Output(fopen('php://memory', 'r+'));

    $command->execute($input, $output);

    expect($indexer->lastChunkSize)->toBe(200);
});

it('defaults the chunk size when no option is given', function (): void {
    $indexer = new FakePriceIndexer();
    $command = new RebuildPriceIndexCommand($indexer);
    $input   = new Input(['marko', 'catalog:price-index:rebuild']);
    $output  = new Output(fopen('php://memory', 'r+'));

    $command->execute($input, $output);

    expect($indexer->lastChunkSize)->toBe(500);
});

it('reports how many entries were rebuilt', function (): void {
    $indexer                 = new FakePriceIndexer();
    $indexer->rebuildReturn  = 42;
    $command                 = new RebuildPriceIndexCommand($indexer);
    $input                   = new Input(['marko', 'catalog:price-index:rebuild']);

    $content = captureRebuildOutput(function (Output $output) use ($command, $input): void {
        $command->execute($input, $output);
    });

    expect($content)->toContain('42');
});
