<?php

declare(strict_types=1);

use Markommerce\Config\Exceptions\InvalidConfigClassException;
use Markommerce\Config\Proxy\ProxyGenerator;
use Markommerce\Config\Proxy\ProxyWriter;
use Markommerce\Config\Registry\ConfigRegistryBuilder;
use Markommerce\Config\Tests\Fakes\FakeScopeRegistry;
use Markommerce\Config\Tests\Fixtures\Proxy\Color;
use Markommerce\Config\Tests\Fixtures\Proxy\EnumPropConfig;
use Markommerce\Config\Tests\Fixtures\Proxy\ReadonlyPropConfig;
use Markommerce\Config\Tests\Fixtures\Proxy\RequiredConstructorProxyConfig;
use Markommerce\Config\Tests\Fixtures\Proxy\SampleConfig;
use Markommerce\Config\Tests\Fixtures\Proxy\SinglePropConfig;
use Markommerce\Config\Tests\Fixtures\Proxy\UnionTypePropConfig;
use Markommerce\Config\ValueObjects\ConfigDefinition;

function buildProxyDefinitions(string $configClass): array
{
    $builder = new ConfigRegistryBuilder();
    $registry = $builder->build([$configClass], new FakeScopeRegistry());
    return $registry->all();
}

it('generates a subclass extending the original config class', function (): void {
    $generator = new ProxyGenerator();
    $definitions = buildProxyDefinitions(SampleConfig::class);

    $source = $generator->generate(SampleConfig::class, $definitions);

    expect($source)->toContain('extends \\' . SampleConfig::class);
});

it('generates a property hook get clause for every #[Config] property', function (): void {
    $generator = new ProxyGenerator();
    $definitions = buildProxyDefinitions(SampleConfig::class);

    $source = $generator->generate(SampleConfig::class, $definitions);

    expect($source)
        ->toContain('$greeting {')
        ->toContain('get =>')
        ->toContain('$itemCount {')
        ->toContain("'greeting'")
        ->toContain("'itemCount'");
});

it('preserves the original property\'s declared type in the override', function (): void {
    $generator = new ProxyGenerator();
    $definitions = buildProxyDefinitions(SampleConfig::class);

    $source = $generator->generate(SampleConfig::class, $definitions);

    expect($source)
        ->toContain('public string $greeting')
        ->toContain('public int $itemCount');
});

it('injects ConfigResolver as the only constructor dependency named __resolver', function (): void {
    $generator = new ProxyGenerator();
    $definitions = buildProxyDefinitions(SampleConfig::class);

    $source = $generator->generate(SampleConfig::class, $definitions);

    expect($source)
        ->toContain('ConfigResolver $__resolver')
        ->toContain('private ConfigResolver $__resolver');
});

it('writes the source to a file path mirroring the original namespace under the target directory', function (): void {
    $generator = new ProxyGenerator();
    $writer = new ProxyWriter();
    $definitions = buildProxyDefinitions(SinglePropConfig::class);

    $source = $generator->generate(SinglePropConfig::class, $definitions);
    $generatedFqn = 'Markommerce\\Config\\Generated\\Markommerce\\Config\\Tests\\Fixtures\\Proxy\\SinglePropConfig_Resolved';

    $targetDir = sys_get_temp_dir() . '/proxy-test-' . uniqid();
    $path = $writer->write($generatedFqn, $source, $targetDir);

    expect($path)->toEndWith('SinglePropConfig_Resolved.php');
    expect(file_exists($path))->toBeTrue();
});

it('creates intermediate directories when the target path nests', function (): void {
    $generator = new ProxyGenerator();
    $writer = new ProxyWriter();
    $definitions = buildProxyDefinitions(SinglePropConfig::class);

    $source = $generator->generate(SinglePropConfig::class, $definitions);
    $generatedFqn = 'Markommerce\\Config\\Generated\\Markommerce\\Config\\Tests\\Fixtures\\Proxy\\SinglePropConfig_Resolved';

    $targetDir = sys_get_temp_dir() . '/proxy-nested-' . uniqid() . '/deeply/nested';
    $path = $writer->write($generatedFqn, $source, $targetDir);

    expect(file_exists($path))->toBeTrue();
    expect(is_dir(dirname($path)))->toBeTrue();
});

it('throws InvalidConfigClassException when a property is readonly', function (): void {
    $generator = new ProxyGenerator();

    $definition = new ConfigDefinition(
        key: 'proxy/readonly.value',
        configClass: ReadonlyPropConfig::class,
        field: 'value',
        axes: [],
        type: 'string',
        defaultValue: null,
        secret: false,
    );

    expect(fn () => $generator->generate(ReadonlyPropConfig::class, [$definition]))
        ->toThrow(InvalidConfigClassException::class);
});

it('throws InvalidConfigClassException when a property has a union type', function (): void {
    $generator = new ProxyGenerator();

    $definition = new ConfigDefinition(
        key: 'proxy/union.value',
        configClass: UnionTypePropConfig::class,
        field: 'value',
        axes: [],
        type: 'int|string',
        defaultValue: 42,
        secret: false,
    );

    expect(fn () => $generator->generate(UnionTypePropConfig::class, [$definition]))
        ->toThrow(InvalidConfigClassException::class);
});

it('throws InvalidConfigClassException when the config class declares a constructor with required parameters', function (): void {
    $generator = new ProxyGenerator();

    $definition = new ConfigDefinition(
        key: 'proxy/ctor.value',
        configClass: RequiredConstructorProxyConfig::class,
        field: 'value',
        axes: [],
        type: 'int',
        defaultValue: 1,
        secret: false,
    );

    expect(fn () => $generator->generate(RequiredConstructorProxyConfig::class, [$definition]))
        ->toThrow(InvalidConfigClassException::class);
});

it('emits enum-typed property hooks using a leading-backslash FQN for the enum type', function (): void {
    $generator = new ProxyGenerator();
    $definitions = buildProxyDefinitions(EnumPropConfig::class);

    $source = $generator->generate(EnumPropConfig::class, $definitions);

    expect($source)->toContain('\\' . Color::class);
});

it('produces source that PHP can parse and require (require + class_exists assertion in a unique-class-per-test fixture)', function (): void {
    $generator = new ProxyGenerator();
    $writer = new ProxyWriter();
    $definitions = buildProxyDefinitions(SinglePropConfig::class);

    $source = $generator->generate(SinglePropConfig::class, $definitions);

    // Verify the source is valid PHP using token_get_all which raises ParseError on invalid syntax
    $tokens = token_get_all($source, TOKEN_PARSE);
    expect($tokens)->not->toBeEmpty();

    // Write and include to confirm class can be loaded
    $generatedFqn = 'Markommerce\\Config\\Generated\\Markommerce\\Config\\Tests\\Fixtures\\Proxy\\SinglePropConfig_Resolved_Parse';
    $source2 = str_replace('SinglePropConfig_Resolved', 'SinglePropConfig_Resolved_Parse', $source);
    $targetDir = sys_get_temp_dir() . '/proxy-parse-' . uniqid();
    $path = $writer->write($generatedFqn, $source2, $targetDir);

    require $path;

    expect(class_exists($generatedFqn))->toBeTrue();
});
