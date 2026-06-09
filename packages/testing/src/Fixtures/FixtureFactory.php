<?php

declare(strict_types=1);

namespace Markommerce\Testing\Fixtures;

use Marko\Core\Event\Event;
use Marko\Core\Event\EventDispatcherInterface;
use Markommerce\Testing\Profile\BootedStore;

/**
 * Abstract base for fluent fixture factories backed by real repositories.
 *
 * Subclasses hold a BootedStore ref, provide sensible unique-per-call
 * defaults, expose fluent with*() override methods, and persist via the
 * real repositories resolved from the store's container.
 */
abstract class FixtureFactory
{
    private static int $counter = 0;

    public function __construct(
        protected readonly BootedStore $store,
    ) {
        $this->ensureEventDispatcherBound();
    }

    /**
     * Create and persist the entity via the real repository.
     *
     * @return object The persisted entity with its auto-assigned ID.
     */
    abstract public function create(): object;

    /**
     * Return the next unique counter value.
     *
     * Uses a static incrementing integer to guarantee uniqueness across
     * all factory calls within a test run without relying on random state.
     */
    protected static function nextCounter(): int
    {
        return ++self::$counter;
    }

    /**
     * Ensure a no-op EventDispatcherInterface is registered in the container.
     *
     * The base Repository constructor declares an optional (nullable)
     * EventDispatcherInterface but the container does not automatically pass
     * null for unbound nullable params when resolving constructor dependencies.
     * Registering a no-op dispatcher once prevents a BindingException when any
     * repository is first resolved.
     */
    private function ensureEventDispatcherBound(): void
    {
        if ($this->store->container()->has(EventDispatcherInterface::class)) {
            return;
        }

        $noOp = new class () implements EventDispatcherInterface
        {
            public function dispatch(Event $event): void {}
        };

        $this->store->container()->instance(EventDispatcherInterface::class, $noOp);
    }
}
