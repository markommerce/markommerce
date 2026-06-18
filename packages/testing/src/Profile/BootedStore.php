<?php

declare(strict_types=1);

namespace Markommerce\Testing\Profile;

use Marko\Core\Container\Container;
use Marko\Core\Module\ModuleManifest;
use Marko\Routing\Exceptions\RouteConflictException;
use Marko\Routing\Exceptions\RouteException;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Markommerce\Scope\Context\ScopeContext;
use Markommerce\Testing\Http\RequestDispatcher;
use Markommerce\Testing\Profile\Exceptions\UndeclaredAxisException;
use ReflectionException;

/**
 * A fully-booted test store: container + scope helper.
 *
 * Created by StoreProfile::boot(). Exposes:
 * - get() to resolve services from the container
 * - inScope() to run closures within an active market/locale scope
 * - entityDirs() for the resolved entity source paths (for schema provisioning)
 */
class BootedStore
{
    private ?RequestDispatcher $dispatcher = null;

    /**
     * @param array<string> $declaredAxes
     * @param array<string> $entityDirs
     * @param array<ModuleManifest> $manifests
     */
    public function __construct(
        private readonly Container $container,
        private readonly array $declaredAxes,
        private readonly array $entityDirs,
        private readonly array $manifests = [],
    ) {}

    /**
     * Resolve a service from the booted container.
     */
    public function get(string $id): mixed
    {
        return $this->container->get($id);
    }

    /**
     * Expose the container directly.
     */
    public function container(): Container
    {
        return $this->container;
    }

    /**
     * Run a closure within an active market/locale scope.
     *
     * Only calls ScopeContext::in() for non-null values whose axis is declared
     * in the profile. Always calls clearAll() after running the closure.
     *
     * @throws UndeclaredAxisException when an axis argument is non-null but not declared in the profile
     */
    public function inScope(
        ?string $market,
        ?string $locale,
        callable $fn,
    ): void {
        if ($market !== null && !in_array('market', $this->declaredAxes, true)) {
            throw UndeclaredAxisException::forAxis('market');
        }

        if ($locale !== null && !in_array('locale', $this->declaredAxes, true)) {
            throw UndeclaredAxisException::forAxis('locale');
        }

        /** @var ScopeContext $scopeContext */
        $scopeContext = $this->container->get(ScopeContext::class);

        try {
            if ($market !== null) {
                $scopeContext->in('market', $market);
            }

            if ($locale !== null) {
                $scopeContext->in('locale', $locale);
            }

            $fn();
        } finally {
            $scopeContext->clearAll();
        }
    }

    /**
     * Return the entity directories for all modules in this profile.
     *
     * Used by SchemaProvisioner to build the matching schema for tests.
     *
     * @return array<string>
     */
    public function entityDirs(): array
    {
        return $this->entityDirs;
    }

    /**
     * Return the module manifests for this booted store.
     *
     * Used by RequestDispatcher to discover routes and global middleware.
     *
     * @return array<ModuleManifest>
     */
    public function manifests(): array
    {
        return $this->manifests;
    }

    /**
     * Dispatch an HTTP request through the full routing + middleware + Latte render pipeline.
     *
     * Uses RoutingBootstrapper to build the router (once, lazily) and GlobalMiddlewareResolver
     * to source global middleware from module declarations. Renders real Latte HTML via the
     * REAL ViewInterface bound in this store's container.
     *
     * Short-circuit responses (302/410/404) from the controller are passed through unchanged.
     *
     * Requires the store to have been booted from a profile that includes routing + layout
     * modules (e.g. StoreProfile::storefront()).
     *
     * @throws ReflectionException|RouteException|RouteConflictException
     */
    public function handle(Request $request): Response
    {
        if ($this->dispatcher === null) {
            $this->dispatcher = new RequestDispatcher($this);
        }

        return $this->dispatcher->dispatch($request);
    }
}
