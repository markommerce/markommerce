<?php

declare(strict_types=1);

use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\Core\Container\Container;
use Marko\Core\Container\ContainerInterface;
use Markommerce\CatalogAttributeIndex\AttributeIndexer;
use Markommerce\Indexer\Command\IndexRebuildCommand;
use Markommerce\Indexer\Contracts\IndexerInterface;
use Markommerce\Indexer\Registry\IndexerRegistry;

// ─── Helpers ──────────────────────────────────────────────────────────────────

function makeStubAttributeIndexer(): AttributeIndexer
{
    return new class () extends AttributeIndexer
    {
        public function __construct()
        {
            // Skip parent constructor — stub for registration tests only.
        }

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
            return 0;
        }
    };
}

function buildAttributeIndexModuleContainer(): Container
{
    $container = new Container();
    $container->instance(ContainerInterface::class, $container);
    $container->singleton(IndexerRegistry::class);

    // Pre-bind a stub AttributeIndexer so the container can resolve it without real deps.
    $container->instance(AttributeIndexer::class, makeStubAttributeIndexer());

    // Load catalog-attribute-index module bindings
    $module = require dirname(__DIR__, 2) . '/module.php';

    foreach ($module['bindings'] ?? [] as $interface => $implementation) {
        $container->bind($interface, $implementation);
    }

    foreach ($module['singletons'] ?? [] as $singleton) {
        $container->singleton($singleton);
    }

    return $container;
}

function makeRegistrationTestOutput(): Output
{
    $stream = fopen('php://memory', 'rw');

    return new Output($stream);
}

function readRegistrationTestOutput(Output $output): string
{
    $reflection = new ReflectionProperty(Output::class, 'stream');
    $stream     = $reflection->getValue($output);
    rewind($stream);

    return stream_get_contents($stream);
}

// ─── Tests ────────────────────────────────────────────────────────────────────

it('registers the AttributeIndexer under the name attribute in the indexer registry after boot', function (): void {
    $container = buildAttributeIndexModuleContainer();
    $module    = require dirname(__DIR__, 2) . '/module.php';

    if (isset($module['boot'])) {
        $container->call($module['boot']);
    }

    $registry = $container->get(IndexerRegistry::class);

    expect($registry->names())->toContain('attribute')
        ->and($registry->get('attribute'))->toBeInstanceOf(IndexerInterface::class);
});

it('rebuilds the attribute index via the unified index:rebuild attribute command', function (): void {
    $container = buildAttributeIndexModuleContainer();
    $module    = require dirname(__DIR__, 2) . '/module.php';

    if (isset($module['boot'])) {
        $container->call($module['boot']);
    }

    $registry = $container->get(IndexerRegistry::class);
    $command  = new IndexRebuildCommand($registry);
    $output   = makeRegistrationTestOutput();
    $input    = new Input(['marko', 'index:rebuild', 'attribute']);

    $exitCode = $command->execute($input, $output);

    expect($exitCode)->toBe(0);
    expect(readRegistrationTestOutput($output))->toContain('attribute');
});

it('includes the attribute index when index:rebuild runs with no name', function (): void {
    $container = buildAttributeIndexModuleContainer();
    $module    = require dirname(__DIR__, 2) . '/module.php';

    if (isset($module['boot'])) {
        $container->call($module['boot']);
    }

    $registry = $container->get(IndexerRegistry::class);
    $command  = new IndexRebuildCommand($registry);
    $output   = makeRegistrationTestOutput();
    $input    = new Input(['marko', 'index:rebuild']);

    $exitCode = $command->execute($input, $output);

    expect($exitCode)->toBe(0);
    expect(readRegistrationTestOutput($output))->toContain('attribute');
});
