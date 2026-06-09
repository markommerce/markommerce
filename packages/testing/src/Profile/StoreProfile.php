<?php

declare(strict_types=1);

namespace Markommerce\Testing\Profile;

use Marko\Config\ConfigDiscovery;
use Marko\Config\ConfigLoader;
use Marko\Config\ConfigMerger;
use Marko\Config\ConfigRepository;
use Marko\Core\Module\ModuleManifest;
use Marko\Database\Connection\ConnectionInterface;
use Markommerce\Testing\Container\ContainerBootstrapper;
use Markommerce\Testing\Module\ModuleResolver;
use Markommerce\Testing\Profile\Exceptions\MissingAppConfigPathException;

/**
 * Fluent builder for a test store profile.
 *
 * Selects root packages, injects scope (market/locale) axis values,
 * and boots into a BootedStore (container + connection + active-scope helper).
 */
class StoreProfile
{
    /** @var array<ModuleManifest> */
    private array $manifests;

    /** @var array<string> */
    private array $markets = [];

    /** @var array<string, list<string>> */
    private array $locales = [];

    /** @var array<string> */
    private array $declaredAxes = [];

    /** The app's root config directory (for fromInstalled). */
    private string $appConfigPath = '';

    /**
     * @param array<ModuleManifest> $manifests
     */
    private function __construct(array $manifests)
    {
        $this->manifests = $manifests;
    }

    /**
     * Build a profile from the given root package names (transitive closure).
     *
     * @param string ...$rootPackages
     */
    public static function of(string $vendorDir, string ...$rootPackages): self
    {
        $resolver = new ModuleResolver($vendorDir);
        $manifests = $resolver->resolveFrom(array_values($rootPackages));

        return new self($manifests);
    }

    /**
     * Build a profile from all installed packages.
     *
     * The app config path is required so the real scope.axes configuration
     * (the merchant's markets and locales) is loaded. Pass the directory
     * containing your application's *.php config files.
     *
     * Alternatively, set the MARKO_APP_CONFIG_PATH environment variable.
     *
     * @throws MissingAppConfigPathException when neither argument nor env var is set
     */
    public static function fromInstalled(string $vendorDir, string $appConfigPath = ''): self
    {
        if ($appConfigPath === '') {
            $appConfigPath = (string) getenv('MARKO_APP_CONFIG_PATH');
        }

        if ($appConfigPath === '') {
            throw new MissingAppConfigPathException();
        }

        $resolver = new ModuleResolver($vendorDir);
        $manifests = $resolver->resolveAllInstalled();

        $profile = new self($manifests);
        $profile->appConfigPath = $appConfigPath;

        return $profile;
    }

    /**
     * Simple preset: catalog + pgsql driver (no market/locale axes).
     *
     * The pgsql driver is required so repositories can be resolved from the
     * container during integration tests.
     */
    public static function simple(string $vendorDir): self
    {
        return self::of($vendorDir, 'markommerce/catalog', 'marko/database-pgsql');
    }

    /**
     * Single-market, two-locale preset: catalog + locale axis with [en, de].
     */
    public static function singleMarketTwoLocales(string $vendorDir): self
    {
        $profile = self::of($vendorDir, 'markommerce/catalog', 'markommerce/locale', 'marko/database-pgsql');
        $profile->declaredAxes[] = 'locale';
        $profile->locales['default'] = ['en', 'de'];

        return $profile;
    }

    /**
     * Two-markets, two-locales preset:
     * catalog + market + locale axes with [us, eu] markets, one locale per market.
     */
    public static function twoMarketsTwoLocales(string $vendorDir): self
    {
        $profile = self::of(
            $vendorDir,
            'markommerce/catalog',
            'markommerce/market',
            'markommerce/locale',
            'markommerce/catalog-market',
            'markommerce/catalog-price-index-market',
            'marko/database-pgsql',
        );
        $profile->declaredAxes[] = 'market';
        $profile->declaredAxes[] = 'locale';
        $profile->markets = ['us', 'eu'];
        $profile->locales = ['us' => ['en'], 'eu' => ['de']];

        return $profile;
    }

    /**
     * Inject explicit market axis values.
     *
     * @param string ...$markets
     */
    public function withMarkets(string ...$markets): self
    {
        $clone = clone $this;
        $clone->markets = array_values($markets);

        if (!in_array('market', $clone->declaredAxes, true)) {
            $clone->declaredAxes[] = 'market';
        }

        return $clone;
    }

    /**
     * Inject a locale value for a specific market.
     */
    public function withLocale(string $market, string $locale): self
    {
        $clone = clone $this;
        $clone->locales[$market][] = $locale;

        if (!in_array('locale', $clone->declaredAxes, true)) {
            $clone->declaredAxes[] = 'locale';
        }

        return $clone;
    }

    /**
     * Inject multiple locale values for a specific market.
     *
     * @param string ...$locales
     */
    public function withLocales(string $market, string ...$locales): self
    {
        $clone = clone $this;
        $clone->locales[$market] = array_merge($clone->locales[$market] ?? [], array_values($locales));

        if (!in_array('locale', $clone->declaredAxes, true)) {
            $clone->declaredAxes[] = 'locale';
        }

        return $clone;
    }

    /**
     * Boot the profile into a BootedStore.
     *
     * Builds the config (merging scope axis values), boots the container
     * via ContainerBootstrapper, and returns a BootedStore ready for use.
     */
    public function boot(ConnectionInterface $connection): BootedStore
    {
        $config = $this->buildConfig();
        $bootstrapper = new ContainerBootstrapper();
        $container = $bootstrapper->bootedContainer($this->manifests, $config, $connection);

        return new BootedStore(
            container: $container,
            declaredAxes: $this->declaredAxes,
            entityDirs: $this->resolveEntityDirs(),
        );
    }

    /**
     * Return the entity directories for all modules in this profile.
     *
     * Only directories that exist on disk are included.
     *
     * @return array<string>
     */
    public function entityDirs(): array
    {
        return $this->resolveEntityDirs();
    }

    /**
     * Return the resolved module set.
     *
     * @return array<ModuleManifest>
     */
    public function modules(): array
    {
        return $this->manifests;
    }

    /**
     * Return the declared scope axes for this profile.
     *
     * @return array<string>
     */
    public function declaredAxes(): array
    {
        return $this->declaredAxes;
    }

    /**
     * Return the declared market names.
     *
     * @return array<string>
     */
    public function markets(): array
    {
        return $this->markets;
    }

    /**
     * Return the declared locale values keyed by market.
     *
     * @return array<string, list<string>>
     */
    public function locales(): array
    {
        return $this->locales;
    }

    /**
     * Return the app config path (for fromInstalled profiles).
     */
    public function appConfigPath(): string
    {
        return $this->appConfigPath;
    }

    /**
     * Build the ConfigRepository for this profile.
     *
     * For fromInstalled profiles: uses ConfigDiscovery over all module paths +
     * the app's config dir (no explicit axis injection).
     *
     * For explicit profiles: uses ConfigDiscovery over module paths with an
     * empty root config dir, then deep-merges the explicit market/locale values.
     */
    private function buildConfig(): ConfigRepository
    {
        $modulePaths = array_filter(
            array_map(fn (ModuleManifest $m) => $m->path, $this->manifests),
            fn (string $path) => $path !== '',
        );

        $discovery = new ConfigDiscovery(new ConfigLoader(), new ConfigMerger());

        if ($this->appConfigPath !== '') {
            // fromInstalled: use real app config
            $rawConfig = $discovery->discover(
                modulePaths: array_values($modulePaths),
                rootConfigPath: $this->appConfigPath,
            );

            return new ConfigRepository($rawConfig);
        }

        // Explicit profile: discover base config, then merge axis values
        $rawConfig = $discovery->discover(
            modulePaths: array_values($modulePaths),
            rootConfigPath: '',
        );

        $rawConfig = $this->mergeAxisValues($rawConfig);

        return new ConfigRepository($rawConfig);
    }

    /**
     * Deep-merge explicit market/locale axis values into the base config.
     *
     * Writes into scope.axes.market.scopes and scope.axes.locale.scopes,
     * building on the defaults that each locale/market package's scope.php declares.
     *
     * @param array<mixed> $config
     * @return array<mixed>
     */
    private function mergeAxisValues(array $config): array
    {
        $merger = new ConfigMerger();

        if ($this->markets !== []) {
            $marketScopes = ['default' => []];

            foreach ($this->markets as $market) {
                $marketScopes[$market] = [];
            }

            $config = $merger->merge($config, [
                'scope' => [
                    'axes' => [
                        'market' => [
                            'scopes' => $marketScopes,
                        ],
                    ],
                ],
            ]);
        }

        if ($this->locales !== []) {
            $localeScopes = ['default' => []];

            foreach ($this->locales as $localeList) {
                foreach ($localeList as $locale) {
                    $localeScopes[$locale] = [];
                }
            }

            $config = $merger->merge($config, [
                'scope' => [
                    'axes' => [
                        'locale' => [
                            'scopes' => $localeScopes,
                        ],
                    ],
                ],
            ]);
        }

        return $config;
    }

    /**
     * Resolve entity directories for all modules in this profile.
     *
     * Returns only directories that actually exist on disk.
     *
     * @return array<string>
     */
    private function resolveEntityDirs(): array
    {
        $dirs = [];

        foreach ($this->manifests as $manifest) {
            if ($manifest->path === '') {
                continue;
            }

            $entityDir = $manifest->path . '/src/Entity';

            if (is_dir($entityDir)) {
                $dirs[] = $entityDir;
            }
        }

        return $dirs;
    }
}
