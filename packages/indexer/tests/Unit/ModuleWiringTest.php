<?php

declare(strict_types=1);

use Marko\Core\Container\Container;
use Marko\Core\Container\ContainerInterface;
use Markommerce\Indexer\Command\IndexRebuildCommand;
use Markommerce\Indexer\Registry\IndexerRegistry;

it('registers the indexer registry and unified index:rebuild command in the indexer module', function (): void {
    $module = require dirname(__DIR__, 2) . '/module.php';

    // IndexerRegistry must be declared as a singleton so all consumers share the same instance
    expect($module['singletons'] ?? [])->toContain(IndexerRegistry::class);

    // IndexRebuildCommand is auto-discovered via #[Command] attribute, but the command class
    // must be explicitly resolvable — verify it is in bindings so the container can wire it
    $container = new Container();
    $container->instance(ContainerInterface::class, $container);

    foreach ($module['bindings'] ?? [] as $interface => $implementation) {
        $container->bind($interface, $implementation);
    }

    foreach ($module['singletons'] ?? [] as $singleton) {
        $container->singleton($singleton);
    }

    // IndexRebuildCommand depends on IndexerRegistry which must be a singleton
    $registry = $container->get(IndexerRegistry::class);
    $sameRegistry = $container->get(IndexerRegistry::class);

    expect($registry)->toBeInstanceOf(IndexerRegistry::class)
        ->and($registry)->toBe($sameRegistry);

    // Verify IndexRebuildCommand is resolvable from the container
    $command = $container->get(IndexRebuildCommand::class);
    expect($command)->toBeInstanceOf(IndexRebuildCommand::class);
});
