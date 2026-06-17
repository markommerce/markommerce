<?php

declare(strict_types=1);

use Marko\Core\Container\ContainerInterface;
use Marko\Database\Entity\EntityMetadataFactory;
use Markommerce\Attribute\Entity\AttributeOption;
use Markommerce\AttributeScope\Entity\AttributeOptionScopedLabels;

return [
    'require' => [
        'markommerce/attribute' => '*',
        'markommerce/scope' => '*',
    ],
    'boot' => static function (ContainerInterface $container): void {
        $metadataFactory = $container->get(EntityMetadataFactory::class);
        $metadataFactory->linkExtenders(AttributeOption::class, [AttributeOptionScopedLabels::class]);
    },
];
