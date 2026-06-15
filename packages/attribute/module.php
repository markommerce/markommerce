<?php

declare(strict_types=1);

use Marko\Core\Container\ContainerInterface;
use Markommerce\Attribute\Registry\AttributeEntityClassMap;
use Markommerce\Attribute\Registry\AttributeTypeRegistry;
use Markommerce\Attribute\Type\BoolType;
use Markommerce\Attribute\Type\DateType;
use Markommerce\Attribute\Type\DecimalType;
use Markommerce\Attribute\Type\EntityRefType;
use Markommerce\Attribute\Type\IntType;
use Markommerce\Attribute\Type\MultiselectType;
use Markommerce\Attribute\Type\SelectType;
use Markommerce\Attribute\Type\TextType;

return [
    'bindings' => [],
    'singletons' => [
        AttributeTypeRegistry::class,
        AttributeEntityClassMap::class,
    ],
    'boot' => static function (ContainerInterface $container): void {
        $registry = $container->get(AttributeTypeRegistry::class);

        $registry->register(new TextType());
        $registry->register(new IntType());
        $registry->register(new DecimalType());
        $registry->register(new BoolType());
        $registry->register(new DateType());
        $registry->register(new SelectType());
        $registry->register(new MultiselectType());
        $registry->register(new EntityRefType());

        $container->instance(AttributeTypeRegistry::class, $registry);
    },
];
