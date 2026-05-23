<?php

declare(strict_types=1);

use Marko\Core\Module\ModuleManifest;
use Marko\Core\Module\ModuleRepositoryInterface;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Markommerce\Layout\Cache\ArtifactWriterInterface;
use Markommerce\Layout\Cache\PreparedTree;
use Markommerce\Layout\Compiler\CompilerInterface;
use Markommerce\Layout\Exception\LayoutException;
use Markommerce\Layout\Middleware\CompileIfStaleMiddleware;

// =============================================================================
// Fakes
// =============================================================================

class MiddlewareFakeCompiler implements CompilerInterface
{
    public int $compileCallCount = 0;
    public ?LayoutException $throwOnCompile = null;

    /** @var array<string, PreparedTree> */
    public array $result = [];

    /**
     * @return array<string, PreparedTree>
     *
     * @throws LayoutException
     */
    public function compile(): array
    {
        if ($this->throwOnCompile !== null) {
            throw $this->throwOnCompile;
        }

        $this->compileCallCount++;

        return $this->result;
    }
}

class MiddlewareFakeArtifactWriter implements ArtifactWriterInterface
{
    public string $path;

    public function __construct(string $path)
    {
        $this->path = $path;
    }

    /**
     * @param array<string, PreparedTree> $trees
     */
    public function write(array $trees): void {}

    public function getPath(): string
    {
        return $this->path;
    }
}

class MiddlewareFakeModuleRepository implements ModuleRepositoryInterface
{
    /** @var array<ModuleManifest> */
    private array $modules;

    /** @param array<ModuleManifest> $modules */
    public function __construct(array $modules)
    {
        $this->modules = $modules;
    }

    /** @return array<ModuleManifest> */
    public function all(): array
    {
        return $this->modules;
    }
}

// =============================================================================
// Helpers
// =============================================================================

function makeTempDir(): string
{
    $tmpDir = sys_get_temp_dir() . '/marko-middleware-test-' . bin2hex(random_bytes(8));
    mkdir($tmpDir, 0755, true);
    return $tmpDir;
}

function makeModuleWithLayoutFile(string $baseDir, string $filename = 'home.php'): array
{
    $layoutDir = $baseDir . '/resources/views/layout';
    mkdir($layoutDir, 0755, true);
    $filePath = $layoutDir . '/' . $filename;
    file_put_contents($filePath, '<?php return null;');
    return [$baseDir, $filePath];
}

function makeArtifactFile(string $dir): string
{
    $artifactPath = $dir . '/layouts.php';
    file_put_contents($artifactPath, '<?php return [];');
    return $artifactPath;
}

function makeNextReturnsOk(): callable
{
    return static fn (Request $request): Response => new Response('ok', 200);
}

// =============================================================================
// Requirement: it collects layout source files from resources/views/layout of registered modules
// =============================================================================

it('collects layout source files from resources/views/layout of registered modules', function (): void {
    $tmpDir = makeTempDir();
    $artifactPath = makeArtifactFile($tmpDir);

    $moduleDir = $tmpDir . '/module';
    [, $sourceFile] = makeModuleWithLayoutFile($moduleDir);

    // Make artifact older than source file so recompile is triggered
    touch($artifactPath, time() - 100);
    touch($sourceFile, time());

    $compiler = new MiddlewareFakeCompiler();
    $writer = new MiddlewareFakeArtifactWriter($artifactPath);
    $module = new ModuleManifest(name: 'test/module', version: '1.0.0', path: $moduleDir);
    $repository = new MiddlewareFakeModuleRepository([$module]);

    $middleware = new CompileIfStaleMiddleware(
        compiler: $compiler,
        artifactWriter: $writer,
        moduleRepository: $repository,
        environment: 'dev',
    );

    $middleware->handle(new Request(), makeNextReturnsOk());

    expect($compiler->compileCallCount)->toBe(1);
});

// =============================================================================
// Requirement: it collects extension source files from resources/views/layout/extensions of registered modules
// =============================================================================

it('collects extension source files from resources/views/layout/extensions of registered modules', function (): void {
    $tmpDir = makeTempDir();
    $artifactPath = makeArtifactFile($tmpDir);

    $moduleDir = $tmpDir . '/module';
    $extensionsDir = $moduleDir . '/resources/views/layout/extensions';
    mkdir($extensionsDir, 0755, true);
    $extensionFile = $extensionsDir . '/product.php';
    file_put_contents($extensionFile, '<?php return null;');

    // Make artifact older than extension file so recompile triggers
    touch($artifactPath, time() - 100);
    touch($extensionFile, time());

    $compiler = new MiddlewareFakeCompiler();
    $writer = new MiddlewareFakeArtifactWriter($artifactPath);
    $module = new ModuleManifest(name: 'test/module', version: '1.0.0', path: $moduleDir);
    $repository = new MiddlewareFakeModuleRepository([$module]);

    $middleware = new CompileIfStaleMiddleware(
        compiler: $compiler,
        artifactWriter: $writer,
        moduleRepository: $repository,
        environment: 'dev',
    );

    $middleware->handle(new Request(), makeNextReturnsOk());

    expect($compiler->compileCallCount)->toBe(1);
});

// =============================================================================
// Requirement: it ignores modules that have no resources/views/layout directory
// =============================================================================

it('ignores modules that have no resources/views/layout directory', function (): void {
    $tmpDir = makeTempDir();
    $artifactPath = makeArtifactFile($tmpDir);

    // Create a module dir with NO resources/views/layout directory
    $moduleDir = $tmpDir . '/module';
    mkdir($moduleDir, 0755, true);

    // Artifact is old — would trigger recompile if any source files were found
    touch($artifactPath, time() - 100);

    $compiler = new MiddlewareFakeCompiler();
    $writer = new MiddlewareFakeArtifactWriter($artifactPath);
    $module = new ModuleManifest(name: 'test/module', version: '1.0.0', path: $moduleDir);
    $repository = new MiddlewareFakeModuleRepository([$module]);

    $middleware = new CompileIfStaleMiddleware(
        compiler: $compiler,
        artifactWriter: $writer,
        moduleRepository: $repository,
        environment: 'dev',
    );

    $middleware->handle(new Request(), makeNextReturnsOk());

    expect($compiler->compileCallCount)->toBe(0);
});

// =============================================================================
// Requirement: it ignores modules that have resources/views/layout but no extensions subdirectory
// =============================================================================

it('ignores modules that have resources/views/layout but no extensions subdirectory', function (): void {
    $tmpDir = makeTempDir();
    $artifactPath = makeArtifactFile($tmpDir);

    $moduleDir = $tmpDir . '/module';
    [, $sourceFile] = makeModuleWithLayoutFile($moduleDir);

    // Artifact is newer than source — no recompile
    touch($sourceFile, time() - 100);
    touch($artifactPath, time());

    $compiler = new MiddlewareFakeCompiler();
    $writer = new MiddlewareFakeArtifactWriter($artifactPath);
    $module = new ModuleManifest(name: 'test/module', version: '1.0.0', path: $moduleDir);
    $repository = new MiddlewareFakeModuleRepository([$module]);

    $middleware = new CompileIfStaleMiddleware(
        compiler: $compiler,
        artifactWriter: $writer,
        moduleRepository: $repository,
        environment: 'dev',
    );

    $middleware->handle(new Request(), makeNextReturnsOk());

    // Should not crash when extensions dir is absent; no recompile triggered
    expect($compiler->compileCallCount)->toBe(0);
});

// =============================================================================
// Requirement: it triggers a recompile when a source file is newer than the artifact in dev environment
// =============================================================================

it('triggers a recompile when a source file is newer than the artifact in dev environment', function (): void {
    $tmpDir = makeTempDir();
    $artifactPath = makeArtifactFile($tmpDir);

    $moduleDir = $tmpDir . '/module';
    [, $sourceFile] = makeModuleWithLayoutFile($moduleDir);

    // Source is newer — triggers recompile
    touch($artifactPath, time() - 100);
    touch($sourceFile, time());

    $compiler = new MiddlewareFakeCompiler();
    $writer = new MiddlewareFakeArtifactWriter($artifactPath);
    $module = new ModuleManifest(name: 'test/module', version: '1.0.0', path: $moduleDir);
    $repository = new MiddlewareFakeModuleRepository([$module]);

    $middleware = new CompileIfStaleMiddleware(
        compiler: $compiler,
        artifactWriter: $writer,
        moduleRepository: $repository,
        environment: 'dev',
    );

    $middleware->handle(new Request(), makeNextReturnsOk());

    expect($compiler->compileCallCount)->toBe(1);
});

// =============================================================================
// Requirement: it does not trigger a recompile when no source files exist
// =============================================================================

it('does not trigger a recompile when no source files exist', function (): void {
    $tmpDir = makeTempDir();
    $artifactPath = makeArtifactFile($tmpDir);

    // Module has layout dir but NO files inside it
    $moduleDir = $tmpDir . '/module';
    mkdir($moduleDir . '/resources/views/layout', 0755, true);

    // Artifact is old — would trigger recompile if any source files were found
    touch($artifactPath, time() - 100);

    $compiler = new MiddlewareFakeCompiler();
    $writer = new MiddlewareFakeArtifactWriter($artifactPath);
    $module = new ModuleManifest(name: 'test/module', version: '1.0.0', path: $moduleDir);
    $repository = new MiddlewareFakeModuleRepository([$module]);

    $middleware = new CompileIfStaleMiddleware(
        compiler: $compiler,
        artifactWriter: $writer,
        moduleRepository: $repository,
        environment: 'dev',
    );

    $middleware->handle(new Request(), makeNextReturnsOk());

    expect($compiler->compileCallCount)->toBe(0);
});

// =============================================================================
// Requirement: it does not recompile based on files in the legacy {module}/layout directory
// =============================================================================

function makeModuleWithLegacyLayoutFile(string $baseDir, string $filename = 'home.php'): array
{
    $layoutDir = $baseDir . '/layout';
    mkdir($layoutDir, 0755, true);
    $filePath = $layoutDir . '/' . $filename;
    file_put_contents($filePath, '<?php return null;');
    return [$baseDir, $filePath];
}

it('does not recompile based on files in the legacy {module}/layout directory', function (): void {
    $tmpDir = makeTempDir();
    $artifactPath = makeArtifactFile($tmpDir);

    $moduleDir = $tmpDir . '/module';

    // Create legacy layout dir with a fresh file that would trigger recompile if inspected
    [, $legacyFile] = makeModuleWithLegacyLayoutFile($moduleDir);
    touch($legacyFile, time());

    // Create new resources/views/layout dir with a stale file (older than artifact)
    $newLayoutDir = $moduleDir . '/resources/views/layout';
    mkdir($newLayoutDir, 0755, true);
    $newFile = $newLayoutDir . '/home.php';
    file_put_contents($newFile, '<?php return null;');
    touch($newFile, time() - 200);

    // Artifact is between the two: older than legacy but newer than new layout file
    touch($artifactPath, time() - 100);

    $compiler = new MiddlewareFakeCompiler();
    $writer = new MiddlewareFakeArtifactWriter($artifactPath);
    $module = new ModuleManifest(name: 'test/module', version: '1.0.0', path: $moduleDir);
    $repository = new MiddlewareFakeModuleRepository([$module]);

    $middleware = new CompileIfStaleMiddleware(
        compiler: $compiler,
        artifactWriter: $writer,
        moduleRepository: $repository,
        environment: 'dev',
    );

    $middleware->handle(new Request(), makeNextReturnsOk());

    // The legacy file is fresh but must NOT trigger recompile
    expect($compiler->compileCallCount)->toBe(0);
});

// =============================================================================
// Requirement 1: it recompiles when a layout source file is newer than the artifact
// =============================================================================

it('recompiles when a layout source file is newer than the artifact', function (): void {
    $tmpDir = makeTempDir();
    $artifactPath = makeArtifactFile($tmpDir);

    $moduleDir = $tmpDir . '/module';
    [, $sourceFile] = makeModuleWithLayoutFile($moduleDir);

    // Make artifact older than source file
    touch($artifactPath, time() - 100);
    touch($sourceFile, time());

    $compiler = new MiddlewareFakeCompiler();
    $writer = new MiddlewareFakeArtifactWriter($artifactPath);
    $module = new ModuleManifest(name: 'test/module', version: '1.0.0', path: $moduleDir);
    $repository = new MiddlewareFakeModuleRepository([$module]);

    $middleware = new CompileIfStaleMiddleware(
        compiler: $compiler,
        artifactWriter: $writer,
        moduleRepository: $repository,
        environment: 'dev',
    );

    $request = new Request();
    $middleware->handle($request, makeNextReturnsOk());

    expect($compiler->compileCallCount)->toBe(1);
});

// =============================================================================
// Requirement 2: it recompiles when the artifact file is missing
// =============================================================================

it('recompiles when the artifact file is missing', function (): void {
    $tmpDir = makeTempDir();
    $artifactPath = $tmpDir . '/nonexistent-layouts.php'; // does NOT exist

    $moduleDir = $tmpDir . '/module';
    makeModuleWithLayoutFile($moduleDir);

    $compiler = new MiddlewareFakeCompiler();
    $writer = new MiddlewareFakeArtifactWriter($artifactPath);
    $module = new ModuleManifest(name: 'test/module', version: '1.0.0', path: $moduleDir);
    $repository = new MiddlewareFakeModuleRepository([$module]);

    $middleware = new CompileIfStaleMiddleware(
        compiler: $compiler,
        artifactWriter: $writer,
        moduleRepository: $repository,
        environment: 'dev',
    );

    $request = new Request();
    $middleware->handle($request, makeNextReturnsOk());

    expect($compiler->compileCallCount)->toBe(1);
});

// =============================================================================
// Requirement 3: it does not recompile when the artifact is newer than all sources
// =============================================================================

it('does not recompile when the artifact is newer than all sources', function (): void {
    $tmpDir = makeTempDir();
    $artifactPath = makeArtifactFile($tmpDir);

    $moduleDir = $tmpDir . '/module';
    [, $sourceFile] = makeModuleWithLayoutFile($moduleDir);

    // Make artifact newer than source file
    touch($sourceFile, time() - 100);
    touch($artifactPath, time());

    $compiler = new MiddlewareFakeCompiler();
    $writer = new MiddlewareFakeArtifactWriter($artifactPath);
    $module = new ModuleManifest(name: 'test/module', version: '1.0.0', path: $moduleDir);
    $repository = new MiddlewareFakeModuleRepository([$module]);

    $middleware = new CompileIfStaleMiddleware(
        compiler: $compiler,
        artifactWriter: $writer,
        moduleRepository: $repository,
        environment: 'dev',
    );

    $request = new Request();
    $middleware->handle($request, makeNextReturnsOk());

    expect($compiler->compileCallCount)->toBe(0);
});

// =============================================================================
// Requirement 4: it lets the request proceed after a successful recompile
// =============================================================================

it('lets the request proceed after a successful recompile', function (): void {
    $tmpDir = makeTempDir();
    $artifactPath = makeArtifactFile($tmpDir);

    $moduleDir = $tmpDir . '/module';
    [, $sourceFile] = makeModuleWithLayoutFile($moduleDir);

    // Source is newer — triggers recompile
    touch($artifactPath, time() - 100);
    touch($sourceFile, time());

    $compiler = new MiddlewareFakeCompiler();
    $writer = new MiddlewareFakeArtifactWriter($artifactPath);
    $module = new ModuleManifest(name: 'test/module', version: '1.0.0', path: $moduleDir);
    $repository = new MiddlewareFakeModuleRepository([$module]);

    $middleware = new CompileIfStaleMiddleware(
        compiler: $compiler,
        artifactWriter: $writer,
        moduleRepository: $repository,
        environment: 'dev',
    );

    $nextCalled = false;
    $next = static function (Request $request) use (&$nextCalled): Response {
        $nextCalled = true;
        return new Response('ok', 200);
    };

    $response = $middleware->handle(new Request(), $next);

    expect($nextCalled)->toBeTrue();
    expect($response->statusCode())->toBe(200);
});

// =============================================================================
// Requirement 5: it lets a compile error propagate instead of swallowing it
// =============================================================================

it('lets a compile error propagate instead of swallowing it', function (): void {
    $tmpDir = makeTempDir();
    $artifactPath = $tmpDir . '/nonexistent-layouts.php'; // missing — triggers recompile

    $moduleDir = $tmpDir . '/module';
    makeModuleWithLayoutFile($moduleDir);

    $compiler = new MiddlewareFakeCompiler();
    $compiler->throwOnCompile = new LayoutException(
        message: 'Compilation failed.',
        context: 'layout: test',
        suggestion: 'Fix the layout.',
    );
    $writer = new MiddlewareFakeArtifactWriter($artifactPath);
    $module = new ModuleManifest(name: 'test/module', version: '1.0.0', path: $moduleDir);
    $repository = new MiddlewareFakeModuleRepository([$module]);

    $middleware = new CompileIfStaleMiddleware(
        compiler: $compiler,
        artifactWriter: $writer,
        moduleRepository: $repository,
        environment: 'dev',
    );

    expect(fn () => $middleware->handle(new Request(), makeNextReturnsOk()))
        ->toThrow(LayoutException::class);
});

// =============================================================================
// Requirement 6: it is inactive outside the dev environment
// =============================================================================

it('is inactive outside the dev environment', function (): void {
    $tmpDir = makeTempDir();
    $artifactPath = $tmpDir . '/nonexistent-layouts.php'; // missing — would trigger recompile in dev

    $moduleDir = $tmpDir . '/module';
    makeModuleWithLayoutFile($moduleDir);

    $compiler = new MiddlewareFakeCompiler();
    $writer = new MiddlewareFakeArtifactWriter($artifactPath);
    $module = new ModuleManifest(name: 'test/module', version: '1.0.0', path: $moduleDir);
    $repository = new MiddlewareFakeModuleRepository([$module]);

    $middleware = new CompileIfStaleMiddleware(
        compiler: $compiler,
        artifactWriter: $writer,
        moduleRepository: $repository,
        environment: 'production',
    );

    $middleware->handle(new Request(), makeNextReturnsOk());

    expect($compiler->compileCallCount)->toBe(0);
});
