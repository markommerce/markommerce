<?php

declare(strict_types=1);

namespace Markommerce\Layout\Middleware;

use Marko\Core\Module\ModuleRepositoryInterface;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\Middleware\MiddlewareInterface;
use Markommerce\Layout\Cache\ArtifactWriterInterface;
use Markommerce\Layout\Compiler\CompilerInterface;
use Markommerce\Layout\Exception\LayoutException;

class CompileIfStaleMiddleware implements MiddlewareInterface
{
    public function __construct(
        private CompilerInterface $compiler,
        private ArtifactWriterInterface $artifactWriter,
        private ModuleRepositoryInterface $moduleRepository,
        private string $environment,
    ) {}

    /**
     * Handle the request: recompile layout artifact if stale (dev/local only).
     *
     * Environment gating: the `environment` constructor parameter is compared
     * against 'dev' and 'local'. In production the middleware is a no-op.
     * Callers (module.php bindings or container configuration) should inject
     * the current APP_ENV value — e.g. `getenv('APP_ENV') ?: 'production'`.
     *
     * @param callable(Request): Response $next
     *
     * @throws LayoutException
     */
    public function handle(
        Request $request,
        callable $next,
    ): Response {
        if (!$this->isDevEnvironment()) {
            return $next($request);
        }

        if ($this->isStale()) {
            $trees = $this->compiler->compile();
            $this->artifactWriter->write($trees);
        }

        return $next($request);
    }

    private function isDevEnvironment(): bool
    {
        return in_array($this->environment, ['dev', 'local'], true);
    }

    /**
     * Check whether any layout source file is newer than the compiled artifact.
     *
     * Cost: one stat() call per source file + one stat() for the artifact.
     * No file parsing is performed.
     */
    private function isStale(): bool
    {
        $artifactPath = $this->artifactWriter->getPath();
        $artifactMtime = is_file($artifactPath) ? filemtime($artifactPath) : false;

        if ($artifactMtime === false) {
            return true;
        }

        $maxSourceMtime = 0;
        foreach ($this->collectSourceFiles() as $file) {
            $mtime = filemtime($file);
            if ($mtime !== false && $mtime > $maxSourceMtime) {
                $maxSourceMtime = $mtime;
            }
        }

        return $maxSourceMtime > $artifactMtime;
    }

    /**
     * Collect all layout PHP source files from all registered modules.
     *
     * @return list<string>
     */
    private function collectSourceFiles(): array
    {
        $files = [];

        foreach ($this->moduleRepository->all() as $module) {
            $layoutDir = $module->path . '/resources/views/layout';

            if (!is_dir($layoutDir)) {
                continue;
            }

            $found = glob($layoutDir . '/*.php');
            if ($found !== false) {
                foreach ($found as $file) {
                    $files[] = $file;
                }
            }

            $extensionsDir = $layoutDir . '/extensions';
            if (is_dir($extensionsDir)) {
                $found = glob($extensionsDir . '/*.php');
                if ($found !== false) {
                    foreach ($found as $file) {
                        $files[] = $file;
                    }
                }
            }
        }

        return $files;
    }
}
