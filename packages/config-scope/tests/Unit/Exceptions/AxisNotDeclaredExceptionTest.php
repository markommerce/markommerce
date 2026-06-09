<?php

declare(strict_types=1);

use Markommerce\ConfigScope\Exceptions\AxisNotDeclaredException;

it(
    'relocates only AxisNotDeclaredException::forPropertyAndAxis() to Markommerce\ConfigScope\Exceptions; the forAxisOnProperty() factory used by the pre-P5 ConfigRegistryBuilder build-time scan is dropped',
    function (): void {
        $reflection = new ReflectionClass(AxisNotDeclaredException::class);
    
        // Confirm the class is in the correct namespace
    expect($reflection->getNamespaceName())->toBe('Markommerce\\ConfigScope\\Exceptions');
    
        // Confirm forPropertyAndAxis() exists
    expect($reflection->hasMethod('forPropertyAndAxis'))->toBeTrue();
    
        // Confirm forAxisOnProperty() does NOT exist (it was dropped)
    expect($reflection->hasMethod('forAxisOnProperty'))->toBeFalse();
    
        // Confirm it's a proper exception class
    expect($reflection->isSubclassOf(Exception::class))->toBeTrue();
    
        // Test the factory method creates a proper exception
    $exception = AxisNotDeclaredException::forPropertyAndAxis('myProperty', 'locale');
        expect($exception)->toBeInstanceOf(AxisNotDeclaredException::class)
            ->and($exception->getMessage())->toContain('myProperty')
            ->and($exception->getMessage())->toContain('locale');
    }
);
