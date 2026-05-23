<?php

declare(strict_types=1);

use Marko\Core\Module\ModuleManifest;
use Marko\Core\Module\ModuleRepositoryInterface;
use Markommerce\Layout\Discovery\DiscoveryResult;
use Markommerce\Layout\Discovery\LayoutDiscovery;
use Markommerce\Layout\Exception\InvalidLayoutFileException;
use Markommerce\Layout\Layout;
use Markommerce\Layout\LayoutExtension;

// --- Helper: fake ModuleRepositoryInterface ---

function makeLayoutModuleRepository(array $modules): ModuleRepositoryInterface
{
    return new class ($modules) implements ModuleRepositoryInterface {
        public function __construct(private array $modules) {}

        public function all(): array
        {
            return $this->modules;
        }
    };
}

// --- Helper: create a temp module directory with resources/views/layout/extensions dirs ---

function makeTempModuleDir(): string
{
    $tmpDir = sys_get_temp_dir() . '/marko-layout-discovery-' . bin2hex(random_bytes(8));
    mkdir($tmpDir . '/resources/views/layout/extensions', 0755, true);
    return $tmpDir;
}

// --- Helper: write a layout PHP file that returns a Layout ---

function writeLayoutFile(string $dir, string $filename, Layout $layout): string
{
    $path = $dir . '/resources/views/layout/' . $filename;
    $serialized = serialize($layout);
    file_put_contents($path, '<?php return unserialize(' . var_export($serialized, true) . ');');
    return $path;
}

// --- Helper: write an extension PHP file that returns a LayoutExtension ---

function writeExtensionFile(string $dir, string $filename, LayoutExtension $extension): string
{
    $path = $dir . '/resources/views/layout/extensions/' . $filename;
    $serialized = serialize($extension);
    file_put_contents($path, '<?php return unserialize(' . var_export($serialized, true) . ');');
    return $path;
}

// --- Helper: write a file that returns a wrong type ---

function writeWrongTypeFile(string $dir, string $filename, string $subdir = 'resources/views/layout'): string
{
    $path = $dir . '/' . $subdir . '/' . $filename;
    file_put_contents($path, '<?php return "this is a string, not a Layout";');
    return $path;
}

// --- Helper: cleanup a temp directory recursively ---

function removeTempDir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }
        $filePath = $dir . '/' . $file;
        if (is_dir($filePath)) {
            removeTempDir($filePath);
        } else {
            unlink($filePath);
        }
    }
    rmdir($dir);
}

// =============================================================================
// Tests
// =============================================================================

it('discovers layout files in resources/views/layout of a module', function (): void {
    $tmpDir = makeTempModuleDir();
    $layout = new Layout(handle: 'test_handle', extends: null, context: [], slots: []);
    writeLayoutFile($tmpDir, 'test_' . bin2hex(random_bytes(4)) . '.php', $layout);

    $module = new ModuleManifest(name: 'test/module', version: '1.0.0', path: $tmpDir);
    $discovery = new LayoutDiscovery(makeLayoutModuleRepository([$module]));

    $result = $discovery->discover();

    expect($result)->toBeInstanceOf(DiscoveryResult::class)
        ->and($result->layouts)->toHaveCount(1)
        ->and($result->extensions)->toHaveCount(0);

    removeTempDir($tmpDir);
});

it('discovers extension files in resources/views/layout/extensions of a module', function (): void {
    $tmpDir = makeTempModuleDir();
    $extension = new LayoutExtension(handle: 'test_handle', operations: []);
    writeExtensionFile($tmpDir, 'test_' . bin2hex(random_bytes(4)) . '.php', $extension);

    $module = new ModuleManifest(name: 'test/module', version: '1.0.0', path: $tmpDir);
    $discovery = new LayoutDiscovery(makeLayoutModuleRepository([$module]));

    $result = $discovery->discover();

    expect($result)->toBeInstanceOf(DiscoveryResult::class)
        ->and($result->layouts)->toHaveCount(0)
        ->and($result->extensions)->toHaveCount(1);

    removeTempDir($tmpDir);
});

it('skips modules with no resources/views/layout directory', function (): void {
    $tmpDir = sys_get_temp_dir() . '/marko-layout-empty-' . bin2hex(random_bytes(8));
    mkdir($tmpDir, 0755, true);

    $module = new ModuleManifest(name: 'test/no-layout', version: '1.0.0', path: $tmpDir);
    $discovery = new LayoutDiscovery(makeLayoutModuleRepository([$module]));

    $result = $discovery->discover();

    expect($result->layouts)->toHaveCount(0)
        ->and($result->extensions)->toHaveCount(0);

    removeTempDir($tmpDir);
});

it('skips a layout directory that has no extensions subdirectory', function (): void {
    $tmpDir = sys_get_temp_dir() . '/marko-layout-no-ext-' . bin2hex(random_bytes(8));
    mkdir($tmpDir . '/resources/views/layout', 0755, true);

    $layout = new Layout(handle: 'test_handle', extends: null, context: [], slots: []);
    $serialized = serialize($layout);
    file_put_contents(
        $tmpDir . '/resources/views/layout/test.php',
        '<?php return unserialize(' . var_export($serialized, true) . ');'
    );

    $module = new ModuleManifest(name: 'test/module', version: '1.0.0', path: $tmpDir);
    $discovery = new LayoutDiscovery(makeLayoutModuleRepository([$module]));

    $result = $discovery->discover();

    expect($result->layouts)->toHaveCount(1)
        ->and($result->extensions)->toHaveCount(0);

    removeTempDir($tmpDir);
});

it('throws InvalidLayoutFileException when a file under resources/views/layout returns a non-Layout value', function (): void {
    $tmpDir = makeTempModuleDir();
    $badFile = $tmpDir . '/resources/views/layout/bad_layout_' . bin2hex(random_bytes(4)) . '.php';
    file_put_contents($badFile, '<?php return "this is a string, not a Layout";');

    $module = new ModuleManifest(name: 'test/module', version: '1.0.0', path: $tmpDir);
    $discovery = new LayoutDiscovery(makeLayoutModuleRepository([$module]));

    expect(fn() => $discovery->discover())
        ->toThrow(InvalidLayoutFileException::class);

    removeTempDir($tmpDir);
});

it('throws InvalidLayoutFileException when a file under resources/views/layout/extensions returns a non-LayoutExtension value', function (): void {
    $tmpDir = makeTempModuleDir();
    $badFile = $tmpDir . '/resources/views/layout/extensions/bad_ext_' . bin2hex(random_bytes(4)) . '.php';
    file_put_contents($badFile, '<?php return 42;');

    $module = new ModuleManifest(name: 'test/module', version: '1.0.0', path: $tmpDir);
    $discovery = new LayoutDiscovery(makeLayoutModuleRepository([$module]));

    expect(fn() => $discovery->discover())
        ->toThrow(InvalidLayoutFileException::class);

    removeTempDir($tmpDir);
});

it('does not discover layouts placed in the legacy {module}/layout directory', function (): void {
    $tmpDir = sys_get_temp_dir() . '/marko-layout-legacy-' . bin2hex(random_bytes(8));

    // Create legacy layout directory with a valid Layout file
    mkdir($tmpDir . '/layout/extensions', 0755, true);
    $layout = new Layout(handle: 'legacy_handle', extends: null, context: [], slots: []);
    $serialized = serialize($layout);
    file_put_contents(
        $tmpDir . '/layout/legacy.php',
        '<?php return unserialize(' . var_export($serialized, true) . ');'
    );

    // Create new resources/views/layout directory (empty — no layout files)
    mkdir($tmpDir . '/resources/views/layout/extensions', 0755, true);

    $module = new ModuleManifest(name: 'test/module', version: '1.0.0', path: $tmpDir);
    $discovery = new LayoutDiscovery(makeLayoutModuleRepository([$module]));

    $result = $discovery->discover();

    expect($result->layouts)->toHaveCount(0)
        ->and($result->extensions)->toHaveCount(0);

    removeTempDir($tmpDir);
});
