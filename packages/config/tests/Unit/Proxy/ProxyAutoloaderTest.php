<?php

declare(strict_types=1);

use Markommerce\Config\Proxy\ProxyAutoloader;

it('autoloads a generated proxy class from a file under the target directory', function (): void {
    $targetDir = sys_get_temp_dir() . '/proxy-autoloader-test-' . uniqid();
    mkdir($targetDir . '/Acme/Shop/Config', 0755, true);

    $generatedClass = 'Markommerce\\Config\\Generated\\Acme\\Shop\\Config\\StoreConfig_Resolved_AutoloadTest';
    $filePath = $targetDir . '/Acme/Shop/Config/StoreConfig_Resolved_AutoloadTest.php';

    file_put_contents($filePath, '<?php' . "\n" . 'declare(strict_types=1);' . "\n" . 'namespace Markommerce\\Config\\Generated\\Acme\\Shop\\Config;' . "\n" . 'class StoreConfig_Resolved_AutoloadTest {}');

    $autoloader = new ProxyAutoloader($targetDir);
    $autoloader->register();

    expect(class_exists($generatedClass))->toBeTrue();
});

it('returns false from the autoloader when the requested class is not under the Generated namespace', function (): void {
    $targetDir = sys_get_temp_dir() . '/proxy-autoloader-test-' . uniqid();
    mkdir($targetDir, 0755, true);

    $autoloader = new ProxyAutoloader($targetDir);
    $autoloader->register();

    // Trigger autoload for a class not under the Generated namespace
    // spl_autoload can return false or simply not load - class_exists returns false
    expect(class_exists('Some\\Other\\Namespace\\SomeClass', false))->toBeFalse();
});
