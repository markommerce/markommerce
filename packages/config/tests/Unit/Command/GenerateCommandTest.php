<?php

declare(strict_types=1);

use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\Core\Container\PreferenceRegistry;
use Markommerce\Config\Attributes\Config;
use Markommerce\Config\Command\GenerateCommand;
use Markommerce\Config\Proxy\PreferenceAwareScanner;
use Markommerce\Config\Proxy\ProxyGenerator;
use Markommerce\Config\Proxy\ProxyLocator;
use Markommerce\Config\Proxy\ProxyWriter;
use Markommerce\Config\Registry\ConfigRegistry;
use Markommerce\Config\Registry\ConfigRegistryBuilder;
use Markommerce\Config\Tests\Fakes\FakeScopeRegistry;
use Markommerce\Config\Tests\Fixtures\Proxy\BaseConfig;
use Markommerce\Config\Tests\Fixtures\Proxy\ExtendedConfig;
use Markommerce\Config\Tests\Fixtures\Proxy\ReadonlyPropConfig;
use Markommerce\Config\ValueObjects\ConfigDefinition;

// --- Fixture config classes ---

class GenerateAlphaConfig
{
    #[Config(key: 'generate/alpha.value')]
    public string $value = 'alpha';
}

class GenerateBetaConfig
{
    #[Config(key: 'generate/beta.value')]
    public int $count = 0;
}

// --- Helpers ---

function makeGenerateRegistry(array $classes): ConfigRegistry
{
    $builder = new ConfigRegistryBuilder();

    return $builder->build($classes, new FakeScopeRegistry());
}

function makeGenerateCommand(ConfigRegistry $registry, string $targetDir, ?PreferenceRegistry $preferenceRegistry = null): GenerateCommand
{
    $preferenceRegistry ??= new PreferenceRegistry();

    return new GenerateCommand(
        configRegistry: $registry,
        preferenceAwareScanner: new PreferenceAwareScanner($preferenceRegistry),
        proxyGenerator: new ProxyGenerator(),
        proxyWriter: new ProxyWriter(),
        proxyLocator: new ProxyLocator(),
        targetDir: $targetDir,
    );
}

function runGenerateCommand(GenerateCommand $command, string ...$args): array
{
    $stream = fopen('php://memory', 'r+');
    $input = new Input(array_merge(['marko', 'config:generate'], $args));
    $output = new Output($stream);

    $exitCode = $command->execute($input, $output);

    rewind($stream);

    return [
        'exitCode' => $exitCode,
        'output'   => (string) stream_get_contents($stream),
    ];
}

function makeGenerateTempDir(): string
{
    $dir = sys_get_temp_dir() . '/generate-command-test-' . uniqid();
    mkdir($dir, 0755, true);

    return $dir;
}

// --- Tests ---

it('generates a proxy file for every registered config class', function (): void {
    $targetDir = makeGenerateTempDir();
    $registry = makeGenerateRegistry([GenerateAlphaConfig::class, GenerateBetaConfig::class]);
    $command = makeGenerateCommand($registry, $targetDir);

    $locator = new ProxyLocator();
    $alphaProxyFqn = $locator->proxyClassFor(GenerateAlphaConfig::class);
    $betaProxyFqn = $locator->proxyClassFor(GenerateBetaConfig::class);

    $alphaPath = $targetDir . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $alphaProxyFqn) . '.php';
    $betaPath = $targetDir . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $betaProxyFqn) . '.php';

    $result = runGenerateCommand($command);

    expect($result['exitCode'])->toBe(0)
        ->and(file_exists($alphaPath))->toBeTrue()
        ->and(file_exists($betaPath))->toBeTrue();
});

it('expands the class list via PreferenceAwareScanner so preferenced subclasses also get proxies', function (): void {
    $targetDir = makeGenerateTempDir();

    // BaseConfig is registered, ExtendedConfig is its preference
    $registry = makeGenerateRegistry([BaseConfig::class]);

    $preferenceRegistry = new PreferenceRegistry();
    $preferenceRegistry->register(BaseConfig::class, ExtendedConfig::class);

    $command = makeGenerateCommand($registry, $targetDir, $preferenceRegistry);

    $locator = new ProxyLocator();
    $baseProxyFqn = $locator->proxyClassFor(BaseConfig::class);
    $extendedProxyFqn = $locator->proxyClassFor(ExtendedConfig::class);

    $basePath = $targetDir . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $baseProxyFqn) . '.php';
    $extendedPath = $targetDir . DIRECTORY_SEPARATOR . str_replace(
        '\\',
        DIRECTORY_SEPARATOR,
        $extendedProxyFqn
    ) . '.php';

    $result = runGenerateCommand($command);

    expect($result['exitCode'])->toBe(0)
        ->and(file_exists($basePath))->toBeTrue()
        ->and(file_exists($extendedPath))->toBeTrue();
});

it('clears the target directory before writing new proxies', function (): void {
    $targetDir = makeGenerateTempDir();

    // Pre-populate with a stale file
    $staleFile = $targetDir . DIRECTORY_SEPARATOR . 'StaleProxy.php';
    file_put_contents($staleFile, '<?php // stale');

    $registry = makeGenerateRegistry([GenerateAlphaConfig::class]);
    $command = makeGenerateCommand($registry, $targetDir);

    $result = runGenerateCommand($command);

    expect($result['exitCode'])->toBe(0)
        ->and(file_exists($staleFile))->toBeFalse();
});

it('writes proxy files at FQN-mirroring paths under the target directory', function (): void {
    $targetDir = makeGenerateTempDir();
    $registry = makeGenerateRegistry([GenerateAlphaConfig::class]);
    $command = makeGenerateCommand($registry, $targetDir);

    $locator = new ProxyLocator();
    $proxyFqn = $locator->proxyClassFor(GenerateAlphaConfig::class);
    $expectedPath = $targetDir . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $proxyFqn) . '.php';

    $result = runGenerateCommand($command);

    expect($result['exitCode'])->toBe(0)
        ->and(file_exists($expectedPath))->toBeTrue();
});

it('prints a summary line counting generated proxies', function (): void {
    $targetDir = makeGenerateTempDir();
    $registry = makeGenerateRegistry([GenerateAlphaConfig::class, GenerateBetaConfig::class]);
    $command = makeGenerateCommand($registry, $targetDir);

    $result = runGenerateCommand($command);

    expect($result['exitCode'])->toBe(0)
        ->and($result['output'])->toContain('Generated 2 proxies');
});

it('exits non-zero when ProxyGenerator throws InvalidConfigClassException', function (): void {
    $targetDir = makeGenerateTempDir();

    // Build a registry with a definition that will cause ProxyGenerator to fail
    // ReadonlyPropConfig has a readonly property which ProxyGenerator rejects
    $definition = new ConfigDefinition(
        key: 'generate/readonly.value',
        configClass: ReadonlyPropConfig::class,
        field: 'value',
        axes: [],
        type: 'string',
        defaultValue: null,
        secret: false,
    );

    $registry = new ConfigRegistry([$definition]);
    $command = makeGenerateCommand($registry, $targetDir);

    $result = runGenerateCommand($command);

    expect($result['exitCode'])->toBe(1);
});

it('warns and exits zero when the registry is empty', function (): void {
    $targetDir = makeGenerateTempDir();
    $registry = new ConfigRegistry([]);
    $command = makeGenerateCommand($registry, $targetDir);

    $result = runGenerateCommand($command);

    expect($result['exitCode'])->toBe(0)
        ->and($result['output'])->toContain('No config classes');
});
