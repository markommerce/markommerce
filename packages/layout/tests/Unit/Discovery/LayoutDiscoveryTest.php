<?php

declare(strict_types=1);

use Marko\Core\Module\ModuleManifest;
use Marko\Core\Module\ModuleRepositoryInterface;
use Markommerce\Layout\Discovery\DiscoveryResult;
use Markommerce\Layout\Discovery\LayoutDiscovery;
use Markommerce\Layout\Exceptions\InvalidLayoutFileException;
use Markommerce\Layout\Layout;
use Markommerce\Layout\LayoutExtension;

// --- Helper: fake ModuleRepositoryInterface ---

function makeLayoutModuleRepository(array $modules): ModuleRepositoryInterface
{
    return new class ($modules) implements ModuleRepositoryInterface
    {
        public function __construct(private array $modules) {}

        public function all(): array
        {
            return $this->modules;
        }
    };
}

// --- Helper: create a temp module directory with layout/extensions dirs ---

function makeTempModuleDir(): string
{
    $tmpDir = sys_get_temp_dir() . '/marko-layout-discovery-' . bin2hex(random_bytes(8));
    mkdir($tmpDir . '/layout/extensions', 0755, true);

    return $tmpDir;
}

// --- Helper: write a layout PHP file that returns a Layout ---

function writeLayoutFile(string $dir, string $filename, Layout $layout): string
{
    $path = $dir . '/layout/' . $filename;
    $serialized = serialize($layout);
    file_put_contents($path, '<?php return unserialize(' . var_export($serialized, true) . ');');

    return $path;
}

// --- Helper: write an extension PHP file that returns a LayoutExtension ---

function writeExtensionFile(string $dir, string $filename, LayoutExtension $extension): string
{
    $path = $dir . '/layout/extensions/' . $filename;
    $serialized = serialize($extension);
    file_put_contents($path, '<?php return unserialize(' . var_export($serialized, true) . ');');

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

it('discovers layout files from a module layout directory', function (): void {
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

it('discovers extension files from a module layout extensions directory', function (): void {
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

it('tags each discovered layout with its source file path', function (): void {
    $tmpDir = makeTempModuleDir();
    $layout = new Layout(handle: 'tagged_handle', extends: null, context: [], slots: []);
    $expectedFile = $tmpDir . '/layout/tagged_layout.php';
    $serialized = serialize($layout);
    file_put_contents($expectedFile, '<?php return unserialize(' . var_export($serialized, true) . ');');

    $module = new ModuleManifest(name: 'test/module', version: '1.0.0', path: $tmpDir);
    $discovery = new LayoutDiscovery(makeLayoutModuleRepository([$module]));

    $result = $discovery->discover();

    expect($result->layouts)->toHaveCount(1)
        ->and($result->layouts[0]->sourceFile)->toBe($expectedFile)
        ->and($result->layouts[0]->layout)->toBeInstanceOf(Layout::class);

    removeTempDir($tmpDir);
});

it('tags each discovered extension with its source file path', function (): void {
    $tmpDir = makeTempModuleDir();
    $extension = new LayoutExtension(handle: 'tagged_handle', operations: []);
    $expectedFile = $tmpDir . '/layout/extensions/tagged_extension.php';
    $serialized = serialize($extension);
    file_put_contents($expectedFile, '<?php return unserialize(' . var_export($serialized, true) . ');');

    $module = new ModuleManifest(name: 'test/module', version: '1.0.0', path: $tmpDir);
    $discovery = new LayoutDiscovery(makeLayoutModuleRepository([$module]));

    $result = $discovery->discover();

    expect($result->extensions)->toHaveCount(1)
        ->and($result->extensions[0]->sourceFile)->toBe($expectedFile)
        ->and($result->extensions[0]->extension)->toBeInstanceOf(LayoutExtension::class);

    removeTempDir($tmpDir);
});

it('returns an empty result for a module with no layout directory', function (): void {
    $tmpDir = sys_get_temp_dir() . '/marko-layout-empty-' . bin2hex(random_bytes(8));
    mkdir($tmpDir, 0755, true);

    $module = new ModuleManifest(name: 'test/no-layout', version: '1.0.0', path: $tmpDir);
    $discovery = new LayoutDiscovery(makeLayoutModuleRepository([$module]));

    $result = $discovery->discover();

    expect($result->layouts)->toHaveCount(0)
        ->and($result->extensions)->toHaveCount(0);

    removeTempDir($tmpDir);
});

it('scans across multiple modules', function (): void {
    $tmpDir1 = makeTempModuleDir();
    $tmpDir2 = makeTempModuleDir();

    $layout1 = new Layout(handle: 'handle_one', extends: null, context: [], slots: []);
    $layout2 = new Layout(handle: 'handle_two', extends: null, context: [], slots: []);
    $extension1 = new LayoutExtension(handle: 'handle_one', operations: []);

    writeLayoutFile($tmpDir1, 'layout1.php', $layout1);
    writeLayoutFile($tmpDir2, 'layout2.php', $layout2);
    writeExtensionFile($tmpDir1, 'ext1.php', $extension1);

    $modules = [
        new ModuleManifest(name: 'test/module-one', version: '1.0.0', path: $tmpDir1),
        new ModuleManifest(name: 'test/module-two', version: '1.0.0', path: $tmpDir2),
    ];
    $discovery = new LayoutDiscovery(makeLayoutModuleRepository($modules));

    $result = $discovery->discover();

    expect($result->layouts)->toHaveCount(2)
        ->and($result->extensions)->toHaveCount(1);

    removeTempDir($tmpDir1);
    removeTempDir($tmpDir2);
});

it('throws a loud error when a layout file does not return a Layout', function (): void {
    $tmpDir = makeTempModuleDir();
    $badFile = $tmpDir . '/layout/bad_layout_' . bin2hex(random_bytes(4)) . '.php';
    file_put_contents($badFile, '<?php return "this is a string, not a Layout";');

    $module = new ModuleManifest(name: 'test/module', version: '1.0.0', path: $tmpDir);
    $discovery = new LayoutDiscovery(makeLayoutModuleRepository([$module]));

    expect(fn () => $discovery->discover())
        ->toThrow(InvalidLayoutFileException::class);

    removeTempDir($tmpDir);
});

it('throws a loud error when an extension file does not return a LayoutExtension', function (): void {
    $tmpDir = makeTempModuleDir();
    $badFile = $tmpDir . '/layout/extensions/bad_ext_' . bin2hex(random_bytes(4)) . '.php';
    file_put_contents($badFile, '<?php return 42;');

    $module = new ModuleManifest(name: 'test/module', version: '1.0.0', path: $tmpDir);
    $discovery = new LayoutDiscovery(makeLayoutModuleRepository([$module]));

    expect(fn () => $discovery->discover())
        ->toThrow(InvalidLayoutFileException::class);

    removeTempDir($tmpDir);
});
