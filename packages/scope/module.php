<?php

declare(strict_types=1);

use Marko\Config\ConfigRepositoryInterface;
use Marko\Core\Container\ContainerInterface;
use Marko\Log\Contracts\LoggerInterface;
use Markommerce\Scope\Context\ScopeContext;
use Markommerce\Scope\Metadata\ScopeMetadataFactory;
use Markommerce\Scope\Middleware\ScopeResolutionMiddleware;
use Markommerce\Scope\Query\ScopedOrderByFactory;
use Markommerce\Scope\Registry\PhpScopeRegistry;
use Markommerce\Scope\Registry\ScopeRegistryInterface;
use Markommerce\Scope\Resolution\ScopeWalker;
use Markommerce\Scope\Resolver\Resolution\ScopeResolutionPipeline;
use Markommerce\Scope\Resolver\Resolution\ScopeResolverChainFactory;
use Markommerce\Scope\Resolver\ScopeResolver;
use Markommerce\Scope\Signature\ScopeSignatureValidator;
use Markommerce\Scope\Signature\SignatureCandidateEnumerator;
use Markommerce\Scope\Storage\DefaultScopeGuard;

return [
    'bindings' => [
        ScopeRegistryInterface::class => function (ContainerInterface $container): PhpScopeRegistry {
            return new PhpScopeRegistry($container->get(ConfigRepositoryInterface::class));
        },
        ScopeResolutionPipeline::class => function (ContainerInterface $container): ScopeResolutionPipeline {
            $logger = null;
            if (interface_exists(LoggerInterface::class)) {
                try {
                    $logger = $container->get(LoggerInterface::class);
                } catch (Throwable) {
                    $logger = null;
                }
            }

            return new ScopeResolutionPipeline(
                scopeRegistry: $container->get(ScopeRegistryInterface::class),
                scopeContext: $container->get(ScopeContext::class),
                scopeResolverChainFactory: $container->get(ScopeResolverChainFactory::class),
                logger: $logger,
            );
        },
    ],
    'singletons' => [
        ScopeContext::class,
        ScopeMetadataFactory::class,
        SignatureCandidateEnumerator::class,
        ScopeSignatureValidator::class,
        ScopeResolver::class,
        ScopedOrderByFactory::class,
        ScopeWalker::class,
        ScopeResolverChainFactory::class,
        ScopeResolutionPipeline::class,
        ScopeResolutionMiddleware::class,
    ],
    'globalMiddleware' => [
        ['class' => ScopeResolutionMiddleware::class, 'priority' => 5],
    ],
    'boot' => function (ContainerInterface $container): void {
        $registry = $container->get(ScopeRegistryInterface::class);
        $factory  = $container->get(ScopeResolverChainFactory::class);

        $defaults = [];
        foreach ($registry->listAxes() as $axisName) {
            $defaults[$axisName] = $registry->getAxis($axisName)->default;
            $factory->for($axisName);
        }
        DefaultScopeGuard::configure($defaults);
    },
];
