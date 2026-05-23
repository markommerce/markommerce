<?php

declare(strict_types=1);

use Marko\Core\Container\ContainerInterface;
use Marko\Core\Module\ModuleRepositoryInterface;
use Marko\Core\Path\ProjectPaths;
use Markommerce\Layout\Cache\ArtifactReader;
use Markommerce\Layout\Cache\ArtifactReaderInterface;
use Markommerce\Layout\Cache\ArtifactWriter;
use Markommerce\Layout\Cache\ArtifactWriterInterface;
use Markommerce\Layout\Cache\PreparedTreeBuilder;
use Markommerce\Layout\Compiler\Compiler;
use Markommerce\Layout\Compiler\CompilerInterface;
use Markommerce\Layout\Compiler\ResolutionPhase;
use Markommerce\Layout\Compiler\ValidationPhase;
use Markommerce\Layout\Discovery\LayoutDiscovery;
use Markommerce\Layout\Middleware\CompileIfStaleMiddleware;
use Markommerce\Layout\Middleware\MarkommerceLayoutMiddleware;
use Markommerce\Layout\Runtime\Renderer;
use Markommerce\Layout\Runtime\RendererInterface;

return [
    'bindings' => [
        ArtifactReaderInterface::class => function (ContainerInterface $container): ArtifactReader {
            $path = $container->get(ProjectPaths::class)->base . '/var/cache/markommerce/layouts.php';
            return new ArtifactReader($path);
        },
        ArtifactWriterInterface::class => function (ContainerInterface $container): ArtifactWriter {
            $path = $container->get(ProjectPaths::class)->base . '/var/cache/markommerce/layouts.php';
            return new ArtifactWriter($path);
        },
        CompilerInterface::class => function (ContainerInterface $container): Compiler {
            return new Compiler(
                layoutDiscovery: $container->get(LayoutDiscovery::class),
                resolutionPhase: $container->get(ResolutionPhase::class),
                validationPhase: $container->get(ValidationPhase::class),
                preparedTreeBuilder: $container->get(PreparedTreeBuilder::class),
            );
        },
        RendererInterface::class => Renderer::class,
        CompileIfStaleMiddleware::class => function (ContainerInterface $container): CompileIfStaleMiddleware {
            return new CompileIfStaleMiddleware(
                compiler: $container->get(CompilerInterface::class),
                artifactWriter: $container->get(ArtifactWriterInterface::class),
                moduleRepository: $container->get(ModuleRepositoryInterface::class),
                environment: (string) (getenv('APP_ENV') ?: 'production'),
            );
        },
        MarkommerceLayoutMiddleware::class => MarkommerceLayoutMiddleware::class,
    ],
    'globalMiddleware' => [
        ['class' => CompileIfStaleMiddleware::class, 'priority' => 28],
        ['class' => MarkommerceLayoutMiddleware::class, 'priority' => 30],
    ],
];
