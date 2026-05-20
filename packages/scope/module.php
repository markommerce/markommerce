<?php

declare(strict_types=1);

use Marko\Config\ConfigRepositoryInterface;
use Marko\Core\Container\ContainerInterface;
use Markommerce\Scope\Context\ScopeContext;
use Markommerce\Scope\Metadata\ScopeMetadataFactory;
use Markommerce\Scope\Query\ScopedOrderByFactory;
use Markommerce\Scope\Registry\PhpScopeRegistry;
use Markommerce\Scope\Registry\ScopeRegistryInterface;
use Markommerce\Scope\Resolution\ScopeWalker;
use Markommerce\Scope\Resolver\ScopeResolver;
use Markommerce\Scope\Signature\ScopeSignatureValidator;
use Markommerce\Scope\Signature\SignatureCandidateEnumerator;

return [
    'bindings' => [
        ScopeRegistryInterface::class => function (ContainerInterface $container): PhpScopeRegistry {
            return new PhpScopeRegistry($container->get(ConfigRepositoryInterface::class));
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
    ],
];
