<?php

declare(strict_types=1);

use Marko\Config\ConfigRepository;
use Marko\Config\ConfigRepositoryInterface;
use Marko\Core\Container\Container;
use Marko\Routing\Http\Request;
use Marko\Routing\RouteCollection;
use Marko\Routing\RouteDiscovery;
use Marko\Routing\RouteMatcher;
use Marko\Routing\RouteMatcherInterface;
use Marko\Routing\Router;
use Markommerce\LayoutDemo\Config\LayoutDemoConfig;
use Markommerce\LayoutDemo\Controller\LayoutDemoController;
use Markommerce\LayoutDemo\Middleware\EnsureLayoutDemoEnabledMiddleware;

// ─── Tests ────────────────────────────────────────────────────────────────────

it('it returns 404 when layout_demo.enabled is false', function (): void {
    $config = new ConfigRepository([
        'layout_demo' => ['enabled' => false],
    ]);

    $layoutDemoConfig = new LayoutDemoConfig($config);
    $ensureMiddleware = new EnsureLayoutDemoEnabledMiddleware($layoutDemoConfig);

    $routes = new RouteCollection();
    $discovery = new RouteDiscovery();
    $controllerRoutes = $discovery->discoverFromClass(LayoutDemoController::class);
    foreach ($controllerRoutes as $route) {
        $routes->add($route);
    }

    $matcher = new RouteMatcher($routes);

    $container = new Container();
    $container->instance(ConfigRepositoryInterface::class, $config);
    $container->instance(RouteMatcherInterface::class, $matcher);
    $container->instance(LayoutDemoConfig::class, $layoutDemoConfig);
    $container->instance(EnsureLayoutDemoEnabledMiddleware::class, $ensureMiddleware);
    $container->instance(LayoutDemoController::class, new LayoutDemoController());

    $router = new Router($matcher, $container, []);
    $request = new Request(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/markommerce/_demo/layout/1']);
    $response = $router->handle($request);

    expect($response->statusCode())->toBe(404);
});
