<?php

declare(strict_types=1);

use Marko\Config\ConfigRepository;
use Marko\Layout\Attributes\Component;
use Marko\Layout\Attributes\Layout;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Middleware;
use Markommerce\ThemeBlankDemo\Component\ThemeBlankShowcaseComponent;
use Markommerce\ThemeBlankDemo\Config\ThemeBlankDemoConfig;
use Markommerce\ThemeBlankDemo\Controller\ThemeBlankDemoController;
use Markommerce\ThemeBlankDemo\Layout\ThemeBlankDemoLayout;
use Markommerce\ThemeBlankDemo\Middleware\EnsureThemeBlankDemoEnabledMiddleware;

it(
    'the #[Get] attribute is placed on the ThemeBlankDemoController action method with path /markommerce/_demo/theme-blank',
    function (): void {
        $reflection = new ReflectionClass(ThemeBlankDemoController::class);

        $classGetAttributes = $reflection->getAttributes(Get::class);
        expect($classGetAttributes)->toBeEmpty();

        $method = $reflection->getMethod('index');
        $methodGetAttributes = $method->getAttributes(Get::class);
        expect($methodGetAttributes)->not->toBeEmpty();

        $getAttr = $methodGetAttributes[0]->newInstance();
        expect($getAttr->path)->toBe('/markommerce/_demo/theme-blank');
    },
);

it(
    'the EnsureThemeBlankDemoEnabledMiddleware is registered via #[Middleware([...])] on the controller action method',
    function (): void {
        $reflection = new ReflectionClass(ThemeBlankDemoController::class);
        $method = $reflection->getMethod('index');
        $middlewareAttributes = $method->getAttributes(Middleware::class);

        expect($middlewareAttributes)->not->toBeEmpty();

        $middlewareAttr = $middlewareAttributes[0]->newInstance();
        expect($middlewareAttr->middleware)->toContain(EnsureThemeBlankDemoEnabledMiddleware::class);
    },
);

it('uses the ThemeBlankDemoLayout component as the layout for the route', function (): void {
    $reflection = new ReflectionClass(ThemeBlankDemoController::class);
    $layoutAttributes = $reflection->getAttributes(Layout::class);

    expect($layoutAttributes)->not->toBeEmpty();

    $layoutAttr = $layoutAttributes[0]->newInstance();
    expect($layoutAttr->component)->toBe(ThemeBlankDemoLayout::class);
});

it('composes the ThemeBlankShowcaseComponent into the content slot of ThemeBlankDemoLayout', function (): void {
    $reflection = new ReflectionClass(ThemeBlankShowcaseComponent::class);
    $componentAttributes = $reflection->getAttributes(Component::class);

    expect($componentAttributes)->not->toBeEmpty();

    $componentAttr = $componentAttributes[0]->newInstance();
    expect($componentAttr->slot)->toBe('content');
});

it(
    'the EnsureThemeBlankDemoEnabledMiddleware injects ThemeBlankDemoConfig via constructor and reads the enabled flag from there',
    function (): void {
        $reflection = new ReflectionClass(EnsureThemeBlankDemoEnabledMiddleware::class);
        $constructor = $reflection->getConstructor();

        expect($constructor)->not->toBeNull();

        $params = $constructor->getParameters();
        $paramTypes = array_map(
            fn ($p) => $p->getType()?->getName(),
            $params,
        );

        expect($paramTypes)->toContain(ThemeBlankDemoConfig::class);
    },
);

it(
    'follows project naming conventions: the ThemeBlankDemoConfig constructor parameter on EnsureThemeBlankDemoEnabledMiddleware is named themeBlankDemoConfig',
    function (): void {
        $reflection = new ReflectionClass(EnsureThemeBlankDemoEnabledMiddleware::class);
        $constructor = $reflection->getConstructor();

        expect($constructor)->not->toBeNull();

        $paramNames = array_map(
            fn ($p) => $p->getName(),
            $constructor->getParameters(),
        );

        expect($paramNames)->toContain('themeBlankDemoConfig');
    },
);

it(
    'ThemeBlankDemoConfig::isEnabled() returns false when theme_blank_demo.enabled config key is absent',
    function (): void {
        $config = new ConfigRepository([]);
        $themeBlankDemoConfig = new ThemeBlankDemoConfig($config);

        expect($themeBlankDemoConfig->isEnabled())->toBeFalse();
    },
);

it(
    'ThemeBlankDemoConfig::isEnabled() returns the config repository\'s boolean value when the key is present',
    function (): void {
        $configEnabled = new ConfigRepository(['theme_blank_demo' => ['enabled' => true]]);
        $themeBlankDemoConfigEnabled = new ThemeBlankDemoConfig($configEnabled);
        expect($themeBlankDemoConfigEnabled->isEnabled())->toBeTrue();

        $configDisabled = new ConfigRepository(['theme_blank_demo' => ['enabled' => false]]);
        $themeBlankDemoConfigDisabled = new ThemeBlankDemoConfig($configDisabled);
        expect($themeBlankDemoConfigDisabled->isEnabled())->toBeFalse();
    },
);
