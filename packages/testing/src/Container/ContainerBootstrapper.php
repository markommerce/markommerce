<?php

declare(strict_types=1);

namespace Markommerce\Testing\Container;

use Closure;
use Marko\Config\ConfigRepositoryInterface;
use Marko\Core\Container\Container;
use Marko\Core\Container\ContainerInterface;
use Marko\Core\Container\PreferenceDiscovery;
use Marko\Core\Container\PreferenceRegistry;
use Marko\Core\Event\EventDispatcher;
use Marko\Core\Event\EventDispatcherInterface;
use Marko\Core\Event\ObserverRegistry;
use Marko\Core\Module\DependencyResolver;
use Marko\Core\Module\ModuleManifest;
use Marko\Core\Module\ModuleRepository;
use Marko\Core\Module\ModuleRepositoryInterface;
use Marko\Core\Path\ProjectPaths;
use Marko\Core\Plugin\InterceptorClassGenerator;
use Marko\Core\Plugin\PluginDiscovery;
use Marko\Core\Plugin\PluginInterceptor;
use Marko\Core\Plugin\PluginRegistry;
use Marko\Database\Connection\ConnectionInterface;
use Markommerce\Config\Cache\CachingConfigResolver;
use Markommerce\Config\ConfigResolver;
use Markommerce\Config\Contracts\ConfigWriterInterface;
use Markommerce\ConfigScope\Contracts\ScopedConfigWriterInterface;
use Markommerce\ConfigScope\ScopedConfigWriter;

/**
 * Reusable bootstrapper for integration test containers.
 *
 * Encapsulates the manual Tier2/Tier3 container-build-and-boot dance:
 * discovers preferences, builds a Container, registers all bindings/singletons
 * from module manifests, wires the plugin interceptor, and calls boot closures
 * in dependency order.
 *
 * Usage:
 *   $bootstrapper = new ContainerBootstrapper();
 *   $container    = $bootstrapper->build($manifests, $config, $connection);
 *   $bootstrapper->wirePlugins($container, $manifests);
 *   $bootstrapper->boot($container, $manifests);
 *
 * Or use the convenience method:
 *   $container = $bootstrapper->bootedContainer($manifests, $config, $connection);
 */
class ContainerBootstrapper
{
    /**
     * Preferences whose `replaces` target must be skipped during discovery so
     * explicit factory bindings in module.php win over the attribute-declared
     * preference.
     *
     * config-scope/module.php supplies an explicit factory for ConfigResolver::class
     * that wraps ScopedConfigResolver in ScopedCachingConfigResolver. If the
     * Preference for ConfigResolver → ScopedConfigResolver were registered, the
     * container would apply it BEFORE checking the factory binding, bypassing
     * the caching wrapper. Same applies to CachingConfigResolver.
     *
     * @var array<class-string>
     */
    private const array PREFERENCE_SKIP_LIST = [
        ConfigResolver::class,
        CachingConfigResolver::class,
    ];

    /**
     * Discover and register all #[Preference] attributes from modules that have a path.
     *
     * Preferences whose `replaces` target is in {@see PREFERENCE_SKIP_LIST} are
     * silently skipped so explicit factory bindings in module.php take precedence.
     *
     * @param array<ModuleManifest> $manifests
     */
    public function discoverPreferences(array $manifests): PreferenceRegistry
    {
        $registry = new PreferenceRegistry();
        $discovery = new PreferenceDiscovery();

        foreach ($manifests as $manifest) {
            if ($manifest->path === '') {
                continue;
            }

            foreach ($discovery->discoverInModule($manifest) as $record) {
                if (in_array($record->replaces, self::PREFERENCE_SKIP_LIST, true)) {
                    continue;
                }

                $registry->register(
                    original: $record->replaces,
                    replacement: $record->replacement,
                    moduleName: $manifest->name,
                    moduleSource: 'vendor',
                );
            }
        }

        return $registry;
    }

    /**
     * Build a fully-wired Container from the given manifests, config, and connection.
     *
     * Steps performed:
     * 1. Discover preferences (skipping PREFERENCE_SKIP_LIST).
     * 2. Construct Container with the PreferenceRegistry.
     * 3. Bind core singletons: ContainerInterface, PreferenceRegistry,
     *    ConfigRepositoryInterface, ConnectionInterface, ProjectPaths,
     *    ModuleRepositoryInterface.
     * 4. Register bindings and singletons from each module manifest (ALL manifests,
     *    even path-less ones).
     * 5. Apply the ConfigWriterInterface → ScopedConfigWriter re-bind when
     *    ScopedConfigWriter is known (reproduces Tier2's manual override).
     *
     * NOTE: Does NOT boot modules or wire plugins — call boot() and wirePlugins()
     * explicitly, in the right order for your use-case.
     *
     * @param array<ModuleManifest> $manifests
     * @param string|null $projectBasePath Optional base path for ProjectPaths. When omitted,
     *        defaults to a per-process temp dir (sys_get_temp_dir()/markommerce-bootstrapper-{pid}).
     *        Pass a unique, per-worker path when running parallel tests that produce layout artifacts
     *        or Vite manifests so workers don't collide on shared compiled files.
     */
    public function build(
        array $manifests,
        ConfigRepositoryInterface $config,
        ConnectionInterface $connection,
        ?string $projectBasePath = null,
    ): Container {
        $preferenceRegistry = $this->discoverPreferences($manifests);

        $container = new Container($preferenceRegistry);

        // ── Core singletons ───────────────────────────────────────────────────
        $container->instance(ContainerInterface::class, $container);
        $container->instance(PreferenceRegistry::class, $preferenceRegistry);
        $container->instance(ConfigRepositoryInterface::class, $config);

        // Event dispatcher: many repositories depend on EventDispatcherInterface.
        // Mirror Application's wiring (new EventDispatcher($container, $observerRegistry))
        // with an empty observer registry so the profile container is self-sufficient
        // and any repository resolves without a per-consumer workaround.
        $observerRegistry = new ObserverRegistry();
        $container->instance(ObserverRegistry::class, $observerRegistry);
        $container->instance(
            EventDispatcherInterface::class,
            new EventDispatcher($container, $observerRegistry),
        );

        // Connection bound as a shared instance — repositories AND the test's
        // isolation transaction must share ONE connection for rollback to work.
        $container->instance(ConnectionInterface::class, $connection);

        // ProjectPaths: use the caller-supplied base path when given, otherwise fall back
        // to a per-process temp dir so ProxyAutoloader has a writable base.
        $basePath = $projectBasePath ?? sys_get_temp_dir() . '/markommerce-bootstrapper-' . getmypid();
        $container->instance(ProjectPaths::class, new ProjectPaths($basePath));

        // ModuleRepository so ConfigClassDiscovery and similar services can
        // iterate all loaded modules.
        $moduleRepository = new ModuleRepository($manifests);
        $container->instance(ModuleRepositoryInterface::class, $moduleRepository);

        // ── Module bindings / singletons ──────────────────────────────────────
        // Process manifests in dependency order (topological sort) so that modules
        // with explicit override factories (e.g. config-scope → ConfigResolver) are
        // registered AFTER their dependencies (e.g. config → ConfigResolver).
        // This ensures later bindings overwrite earlier ones, matching the Tier2 behavior
        // where config-scope bindings are intentionally applied last.
        $resolver = new DependencyResolver();
        $ordered = $resolver->resolve($manifests);

        foreach ($ordered as $manifest) {
            $this->registerManifestBindings($container, $manifest);
        }

        // ── ConfigWriterInterface re-bind ─────────────────────────────────────
        // config/module.php binds ConfigWriterInterface → ConfigWriter (string
        // binding). The container checks preferences on the *initial* $id, not
        // on the resolved binding target, so ConfigWriterInterface → ConfigWriter
        // never triggers the ConfigWriter → ScopedConfigWriter preference.
        // We reproduce the Tier2 fix: rebind ConfigWriterInterface directly to
        // ScopedConfigWriter when it is available (i.e. when config-scope is
        // in the module set).
        if (class_exists(ScopedConfigWriter::class)) {
            $preference = $preferenceRegistry->getPreference(\Markommerce\Config\ConfigWriter::class);

            if ($preference !== null) {
                $container->bind(ConfigWriterInterface::class, $preference);

                // Also bind ScopedConfigWriterInterface so callers can resolve it directly.
                if (interface_exists(ScopedConfigWriterInterface::class)) {
                    $container->bind(ScopedConfigWriterInterface::class, $preference);
                }
            }
        }

        return $container;
    }

    /**
     * Wire the PluginInterceptor and discover+register all plugins from modules
     * with a path.
     *
     * MUST be called BEFORE any plugin-decorated service is resolved from the
     * container — otherwise interception silently no-ops.
     *
     * @param array<ModuleManifest> $manifests
     */
    public function wirePlugins(Container $container, array $manifests): void
    {
        $pluginRegistry = new PluginRegistry();
        $interceptor = new PluginInterceptor($container, $pluginRegistry, new InterceptorClassGenerator());

        $container->setPluginInterceptor($interceptor);
        $container->instance(PluginInterceptor::class, $interceptor);
        $container->instance(PluginRegistry::class, $pluginRegistry);

        $discovery = new PluginDiscovery();

        foreach ($manifests as $manifest) {
            if ($manifest->path === '') {
                continue;
            }

            foreach ($discovery->discoverInModule($manifest) as $definition) {
                $pluginRegistry->register($definition);
            }
        }
    }

    /**
     * Boot all modules in dependency order.
     *
     * Resolves the topological boot order via DependencyResolver, then calls
     * each manifest's boot closure (if present) via the container so
     * dependencies are auto-injected.
     *
     * @param array<ModuleManifest> $manifests
     */
    public function boot(Container $container, array $manifests): void
    {
        $resolver = new DependencyResolver();
        $ordered = $resolver->resolve($manifests);

        foreach ($ordered as $manifest) {
            if ($manifest->boot instanceof Closure) {
                $container->call($manifest->boot);
            }
        }
    }

    /**
     * Convenience method: build + wirePlugins + boot in one call.
     *
     * Returns a fully-booted, plugin-interceptor-wired Container ready for use.
     *
     * @param array<ModuleManifest> $manifests
     * @param string|null $projectBasePath Optional base path for ProjectPaths — forwarded to build().
     */
    public function bootedContainer(
        array $manifests,
        ConfigRepositoryInterface $config,
        ConnectionInterface $connection,
        ?string $projectBasePath = null,
    ): Container {
        $container = $this->build($manifests, $config, $connection, $projectBasePath);
        $this->wirePlugins($container, $manifests);
        $this->boot($container, $manifests);

        return $container;
    }

    /**
     * Register bindings and singletons declared in a single ModuleManifest.
     *
     * Handles both the simple form (`singletons[] = ClassName`) and the keyed
     * form (`singletons[InterfaceName] = ClassName`) that `config/module.php` uses.
     *
     * In module.php files the singletons array may be a plain list
     * (`[ConfigRegistry::class, ...]`) where PHP assigns integer keys at runtime.
     * The ManifestParser stores these directly, so `$key` is numeric in list form
     * even though the ModuleManifest PHPDoc declares `array<string, string|Closure>`.
     * We detect list-style entries by checking `ctype_digit($key)`.
     */
    private function registerManifestBindings(Container $container, ModuleManifest $manifest): void
    {
        foreach ($manifest->bindings as $interface => $implementation) {
            $container->bind($interface, $implementation);
        }

        // NOTE: ModuleManifest PHPDoc declares `array<string, string|Closure>` for singletons,
        // but module.php files use list syntax (`[ConfigRegistry::class, ...]`) which
        // PHP stores with integer keys at runtime. We normalise before iteration.
        $singletons = $manifest->singletons;

        foreach ($singletons as $key => $value) {
            // Cast to string so the same logic works for both int keys (list form) and
            // string keys (map form) without triggering PHP's ctype_digit(int) deprecation.
            $stringKey = (string) $key;

            // List-style: singletons[] = ClassName  →  key is "0", "1", … at runtime.
            // Map-style:  singletons[InterfaceName] = ClassName  →  key is the FQCN.
            if (ctype_digit($stringKey)) {
                // Simple form: register the value class as a singleton.
                // List-style singletons are always string class names (never Closures).
                if (is_string($value)) {
                    $container->singleton($value);
                }
            } else {
                // Keyed form: bind the interface to the implementation, then share it
                $container->bind($stringKey, $value);
                $container->singleton($stringKey);
            }
        }
    }
}
