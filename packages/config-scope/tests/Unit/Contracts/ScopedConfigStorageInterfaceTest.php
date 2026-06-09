<?php

declare(strict_types=1);

use Markommerce\ConfigScope\Contracts\ScopedConfigStorageInterface;

it(
    'declares loadOverrides, loadManyOverrides, saveOverride, and deleteOverride methods on ScopedConfigStorageInterface',
    function (): void {
        $reflection = new ReflectionClass(ScopedConfigStorageInterface::class);
    
        expect($reflection->isInterface())->toBeTrue()
            ->and($reflection->hasMethod('loadOverrides'))->toBeTrue()
            ->and($reflection->hasMethod('loadManyOverrides'))->toBeTrue()
            ->and($reflection->hasMethod('saveOverride'))->toBeTrue()
            ->and($reflection->hasMethod('deleteOverride'))->toBeTrue();
    }
);
