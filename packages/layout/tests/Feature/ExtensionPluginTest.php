<?php

declare(strict_types=1);

use Marko\Core\Container\Container;
use Marko\Core\Container\PreferenceRegistry;
use Marko\Core\Plugin\InterceptorClassGenerator;
use Marko\Core\Plugin\PluginDefinition;
use Marko\Core\Plugin\PluginInterceptor;
use Marko\Core\Plugin\PluginRegistry;
use Markommerce\Layout\Contracts\ExtensionAttribute;
use Markommerce\Layout\ExtensibleData;
use Markommerce\Layout\ExtensionBag;

// ---------------------------------------------------------------------------
// Test fixtures
// ---------------------------------------------------------------------------

/**
 * A typed extension attribute for review stars.
 * Defined by a hypothetical "markommerce/reviews" module.
 */
readonly class EPT_ReviewStarsExtension implements ExtensionAttribute
{
    public function __construct(public float $stars) {}
}

/**
 * A concrete data DTO that extends ExtensibleData.
 * Immutable value object (readonly class is fine for DTOs).
 */
readonly class EPT_ProductCardData extends ExtensibleData
{
    public function __construct(
        public int $id,
        public string $name,
        ExtensionBag $extensions = new ExtensionBag(),
    ) {
        parent::__construct($extensions);
    }
}

/**
 * A component that returns a typed data DTO.
 *
 * IMPORTANT: This class is NOT readonly. Marko's Plugin mechanism uses the
 * concrete subclass strategy for classes without interfaces, and PHP does not
 * allow subclassing a readonly class with new state. Component classes that
 * expose a plugin-extensible data() method MUST NOT be readonly class.
 */
class EPT_ProductCardComponent
{
    public function data(int $id): EPT_ProductCardData
    {
        return new EPT_ProductCardData(id: $id, name: "Product #$id");
    }
}

/**
 * An after-plugin on EPT_ProductCardComponent::data().
 * Augments the returned DTO with review stars.
 *
 * After-plugin signature: methodName(mixed $result, ...$originalArgs): mixed
 */
class EPT_ReviewStarsPlugin
{
    public function data(
        mixed $result,
        int $id,
    ): EPT_ProductCardData {
        /** @var EPT_ProductCardData $result */
        return $result->withExtension(new EPT_ReviewStarsExtension(stars: 4.8));
    }
}

// ---------------------------------------------------------------------------
// Helper
// ---------------------------------------------------------------------------

function makeEPTContainer(): array
{
    $registry = new PluginRegistry();
    $container = new Container(new PreferenceRegistry());
    $interceptor = new PluginInterceptor($container, $registry, new InterceptorClassGenerator());
    $container->setPluginInterceptor($interceptor);

    return ['container' => $container, 'registry' => $registry];
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

it('applies an extension to a component data DTO via a Marko after-plugin on the data method', function (): void {
    ['container' => $container, 'registry' => $registry] = makeEPTContainer();

    $registry->register(new PluginDefinition(
        pluginClass: EPT_ReviewStarsPlugin::class,
        targetClass: EPT_ProductCardComponent::class,
        afterMethods: ['data' => ['pluginMethod' => 'data', 'sortOrder' => 10]],
    ));

    $component = $container->get(EPT_ProductCardComponent::class);
    $data = $component->data(id: 1);

    expect($data)->toBeInstanceOf(EPT_ProductCardData::class)
        ->and($data->id)->toBe(1)
        ->and($data->name)->toBe('Product #1')
        ->and($data->extensions->get(EPT_ReviewStarsExtension::class))->toBeInstanceOf(EPT_ReviewStarsExtension::class)
        ->and($data->extensions->get(EPT_ReviewStarsExtension::class)->stars)->toBe(4.8);
});

it('preserves plugin interception when the component is resolved through the container', function (): void {
    ['container' => $container, 'registry' => $registry] = makeEPTContainer();

    $registry->register(new PluginDefinition(
        pluginClass: EPT_ReviewStarsPlugin::class,
        targetClass: EPT_ProductCardComponent::class,
        afterMethods: ['data' => ['pluginMethod' => 'data', 'sortOrder' => 10]],
    ));

    // Resolve through the container (interception is active)
    $viaContainer = $container->get(EPT_ProductCardComponent::class);
    $interceptedData = $viaContainer->data(id: 99);

    // Direct instantiation bypasses the container — no interception
    $direct = new EPT_ProductCardComponent();
    $uninterceptedData = $direct->data(id: 99);

    expect($interceptedData->extensions->get(EPT_ReviewStarsExtension::class))
        ->toBeInstanceOf(EPT_ReviewStarsExtension::class)
        ->and($uninterceptedData->extensions->get(EPT_ReviewStarsExtension::class))
        ->toBeNull();
});
