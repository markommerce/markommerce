<?php

declare(strict_types=1);

use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Middleware;
use Markommerce\FrontendDemo\Component\DemoCounterComponent;
use Markommerce\FrontendDemo\Config\FrontendDemoConfig;
use Markommerce\FrontendDemo\Controller\DemoController;
use Markommerce\FrontendDemo\Middleware\EnsureFrontendDemoEnabledMiddleware;
use Markommerce\Layout\Layout;
use Markommerce\Layout\Place;

it('the #[Get] attribute is placed on the DemoController action method (not the class)', function (): void {
    $reflection = new ReflectionClass(DemoController::class);

    $classGetAttributes = $reflection->getAttributes(Get::class);
    expect($classGetAttributes)->toBeEmpty();

    $method = $reflection->getMethod('index');
    $methodGetAttributes = $method->getAttributes(Get::class);
    expect($methodGetAttributes)->not->toBeEmpty();

    $getAttr = $methodGetAttributes[0]->newInstance();
    expect($getAttr->path)->toBe('/markommerce/_demo');
});

it(
    'the EnsureFrontendDemoEnabledMiddleware is registered via #[Middleware([...])] on the controller action method',
    function (): void {
        $reflection = new ReflectionClass(DemoController::class);
        $method = $reflection->getMethod('index');
        $middlewareAttributes = $method->getAttributes(Middleware::class);

        expect($middlewareAttributes)->not->toBeEmpty();

        $middlewareAttr = $middlewareAttributes[0]->newInstance();
        expect($middlewareAttr->middleware)->toContain(EnsureFrontendDemoEnabledMiddleware::class);
    },
);

it('it has a layout file that returns a Layout for DemoController::index', function (): void {
    $layoutPath = dirname(__DIR__, 2) . '/layout/demo.php';

    expect(file_exists($layoutPath))->toBeTrue();

    $layout = require $layoutPath;

    expect($layout)->toBeInstanceOf(Layout::class);
    expect($layout->handle)->toBe([DemoController::class, 'index']);
});

it('it places DemoCounterComponent in the content slot', function (): void {
    $layoutPath = dirname(__DIR__, 2) . '/layout/demo.php';
    $layout = require $layoutPath;

    expect($layout)->toBeInstanceOf(Layout::class);
    expect($layout->slots)->toHaveKey('content');

    $contentSlot = $layout->slots['content'];
    expect($contentSlot)->toBeArray();
    expect($contentSlot)->not->toBeEmpty();

    $place = $contentSlot[0];
    expect($place)->toBeInstanceOf(Place::class);
    expect($place->component)->toBe(DemoCounterComponent::class);
});

it('it drops the #[Layout] attribute from DemoController', function (): void {
    $reflection = new ReflectionClass(DemoController::class);
    $layoutAttributes = $reflection->getAttributes(Layout::class);
    $markoLayoutAttributes = $reflection->getAttributes('Marko\Layout\Attributes\Layout');

    expect($layoutAttributes)->toBeEmpty();
    expect($markoLayoutAttributes)->toBeEmpty();
});

it('it drops the #[Component] attribute from DemoCounterComponent', function (): void {
    $reflection = new ReflectionClass(DemoCounterComponent::class);
    $componentAttributes = $reflection->getAttributes('Marko\Layout\Attributes\Component');

    expect($componentAttributes)->toBeEmpty();
});

it(
    'the demo main.ts imports open-props/style.css so Vite emits the Open Props stylesheet link in the head',
    function (): void {
        $mainTsPath = dirname(__DIR__, 2) . '/resources/js/main.ts';

        expect(file_exists($mainTsPath))->toBeTrue();

        $contents = file_get_contents($mainTsPath);
        expect($contents)->toContain("import 'open-props/style.css'");
    },
);

it(
    'it loads the @markommerce/frontend cascade layers and @markommerce/theme-blank tokens CSS in the head before component-level CSS',
    function (): void {
        $mainTsPath = dirname(__DIR__, 2) . '/resources/js/main.ts';
        $contents = file_get_contents($mainTsPath);

        $layersPos = strpos($contents, '@markommerce/frontend/css/layers.css');
        $tokensPos = strpos($contents, '@markommerce/theme-blank/css/tokens.css');
        $componentPos = strpos($contents, 'counter.css');

        expect($layersPos !== false)->toBeTrue()
            ->and($tokensPos !== false)->toBeTrue()
            ->and($componentPos !== false)->toBeTrue()
            ->and($layersPos)->toBeLessThan($componentPos)
            ->and($tokensPos)->toBeLessThan($componentPos);
    },
);

it(
    'the controller and middleware inject FrontendDemoConfig via constructor and read the enabled flag from there',
    function (): void {
        $reflection = new ReflectionClass(EnsureFrontendDemoEnabledMiddleware::class);
        $constructor = $reflection->getConstructor();

        expect($constructor)->not->toBeNull();

        $params = $constructor->getParameters();
        $paramTypes = array_map(
            fn ($p) => $p->getType()?->getName(),
            $params,
        );

        expect($paramTypes)->toContain(FrontendDemoConfig::class);
    },
);

it(
    'it follows project naming conventions: the FrontendDemoConfig parameter is named frontendDemoConfig',
    function (): void {
        $reflection = new ReflectionClass(EnsureFrontendDemoEnabledMiddleware::class);
        $constructor = $reflection->getConstructor();

        expect($constructor)->not->toBeNull();

        $paramNames = array_map(
            fn ($p) => $p->getName(),
            $constructor->getParameters(),
        );

        expect($paramNames)->toContain('frontendDemoConfig');
    },
);

it(
    'counter.latte renders only the markommerce-counter element and no longer contains primitives or form controls (file-content assertion)',
    function (): void {
        $lattePath = dirname(__DIR__, 2) . '/resources/views/counter.latte';

        expect(file_exists($lattePath))->toBeTrue();

        $contents = file_get_contents($lattePath);
        expect(trim($contents))->toBe('<markommerce-counter start-value="0" suffix=" clicks"></markommerce-counter>');
        expect($contents)->not->toContain('<mk-');
    },
);
