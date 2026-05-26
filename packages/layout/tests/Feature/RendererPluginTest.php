<?php

declare(strict_types=1);

use Marko\Core\Container\Container;
use Marko\Core\Container\PreferenceRegistry;
use Marko\Core\Plugin\InterceptorClassGenerator;
use Marko\Core\Plugin\PluginDefinition;
use Marko\Core\Plugin\PluginInterceptor;
use Marko\Core\Plugin\PluginRegistry;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\View\ViewInterface;
use Markommerce\Layout\Cache\PreparedPlace;
use Markommerce\Layout\Cache\PreparedTree;
use Markommerce\Layout\Contracts\ExtensionAttribute;
use Markommerce\Layout\ExtensibleData;
use Markommerce\Layout\ExtensionBag;
use Markommerce\Layout\Runtime\Renderer;

// ---------------------------------------------------------------------------
// Test fixtures
// ---------------------------------------------------------------------------

readonly class RPT_BadgeExtension implements ExtensionAttribute
{
    public function __construct(public string $label) {}
}

readonly class RPT_CardData extends ExtensibleData
{
    public function __construct(
        public string $title,
        ExtensionBag $extensions = new ExtensionBag(),
    ) {
        parent::__construct($extensions);
    }
}

/**
 * IMPORTANT: NOT readonly — Marko Plugin subclass strategy requires non-readonly.
 */
class RPT_CardComponent
{
    public function data(string $title = 'Default'): RPT_CardData
    {
        return new RPT_CardData(title: $title);
    }
}

class RPT_BadgePlugin
{
    public function data(
        mixed $result,
        string $title = 'Default',
    ): RPT_CardData {
        /** @var RPT_CardData $result */
        return $result->withExtension(new RPT_BadgeExtension(label: 'NEW'));
    }
}

/**
 * A fake view that records what data it receives.
 */
class RPT_RecordingView implements ViewInterface
{
    /** @var array<array{template: string, data: array<string, mixed>}> */
    public array $calls = [];

    public function render(
        string $template,
        array $data = [],
    ): Response {
        return Response::html($this->renderToString($template, $data));
    }

    public function renderToString(
        string $template,
        array $data = [],
    ): string {
        $this->calls[] = ['template' => $template, 'data' => $data];

        return "<div data-template=\"$template\"></div>";
    }
}

// ---------------------------------------------------------------------------
// Helper
// ---------------------------------------------------------------------------

function makeRPTContainer(): Container
{
    $registry = new PluginRegistry();
    $container = new Container(new PreferenceRegistry());
    $interceptor = new PluginInterceptor($container, $registry, new InterceptorClassGenerator());
    $container->setPluginInterceptor($interceptor);

    $registry->register(new PluginDefinition(
        pluginClass: RPT_BadgePlugin::class,
        targetClass: RPT_CardComponent::class,
        afterMethods: ['data' => ['pluginMethod' => 'data', 'sortOrder' => 10]],
    ));

    return $container;
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

it('renders an extension field added to a component DTO via a Marko plugin', function (): void {
    $container = makeRPTContainer();
    $view = new RPT_RecordingView();

    $tree = new PreparedTree(
        handleKey: 'storefront',
        template: null,
        slots: [
            'content' => [
                new PreparedPlace(
                    component: RPT_CardComponent::class,
                    name: 'card',
                    props: ['title' => 'Plugin Test'],
                    slots: [],
                ),
            ],
        ],
        context: [],
    );

    $renderer = new Renderer($view, $container);
    $renderer->render($tree, new Request(), []);

    // The plugin adds RPT_BadgeExtension to the DTO
    // Find the call for RPT_CardComponent
    $cardCall = array_find($view->calls, fn (array $c) => $c['template'] === RPT_CardComponent::class);

    expect($cardCall)->not->toBeNull();

    /** @var array<string, mixed> $data */
    $data = $cardCall['data'];

    // The extensions bag should contain the badge extension added by the plugin
    expect($data)->toHaveKey('extensions');
    expect($data['extensions'])->toBeInstanceOf(ExtensionBag::class);

    /** @var ExtensionBag $bag */
    $bag = $data['extensions'];
    $badge = $bag->get(RPT_BadgeExtension::class);

    expect($badge)->toBeInstanceOf(RPT_BadgeExtension::class)
        ->and($badge->label)->toBe('NEW');
});
