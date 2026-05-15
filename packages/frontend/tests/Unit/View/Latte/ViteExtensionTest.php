<?php

declare(strict_types=1);

use Latte\Engine;
use Latte\Runtime\Html;
use Latte\Runtime\HtmlStringable;
use Marko\Config\ConfigRepository;
use Marko\Core\Path\ProjectPaths;
use Marko\Vite\Exceptions\ViteConfigurationException;
use Marko\Vite\Vite;
use Markommerce\Frontend\Exceptions\ViteHelperException;
use Markommerce\Frontend\View\Latte\ViteExtension;

describe('ViteExtension', function (): void {
    it('registers a Latte function named vite that returns Html-typed output', function (): void {
        $vite = $this->createMock(Vite::class);

        $extension = new ViteExtension($vite, 'resources/js/app.ts');

        $functions = $extension->getFunctions();

        expect($functions)->toHaveKey('vite');
        expect($functions['vite'])->toBeCallable();
    });

    it('delegates to Marko\\Vite\\Vite::headTags with the explicit entry argument', function (): void {
        $vite = $this->createMock(Vite::class);
        $vite->expects($this->once())
            ->method('headTags')
            ->with('resources/js/custom.ts')
            ->willReturn('<script type="module" src="/build/custom.js"></script>');

        $extension = new ViteExtension($vite, 'resources/js/app.ts');
        $result = $extension->vite('resources/js/custom.ts');

        expect($result)->toBeInstanceOf(Html::class);
        expect((string) $result)->toBe('<script type="module" src="/build/custom.js"></script>');
    });

    it('falls back to the configured default entry when no argument is passed', function (): void {
        $vite = $this->createMock(Vite::class);
        $vite->expects($this->once())
            ->method('headTags')
            ->with('resources/js/app.ts')
            ->willReturn('<script type="module" src="/build/app.js"></script>');

        $extension = new ViteExtension($vite, 'resources/js/app.ts');
        $result = $extension->vite();

        expect((string) $result)->toBe('<script type="module" src="/build/app.js"></script>');
    });

    it('returns the Html wrapper so Latte does not escape the resulting tags', function (): void {
        $vite = $this->createMock(Vite::class);
        $vite->method('headTags')->willReturn('<script type="module" src="/build/app.js"></script>');

        $extension = new ViteExtension($vite, 'resources/js/app.ts');
        $result = $extension->vite();

        expect($result)->toBeInstanceOf(Html::class);
        expect($result)->toBeInstanceOf(HtmlStringable::class);
    });

    it('throws ViteHelperException with context and suggestion when the explicit entry is empty string', function (): void {
        $vite = $this->createMock(Vite::class);
        $vite->expects($this->never())->method('headTags');

        $extension = new ViteExtension($vite, 'resources/js/app.ts');

        expect(fn () => $extension->vite(''))->toThrow(ViteHelperException::class);
    });

    it('propagates ViteConfigurationException from the underlying Vite service when configuration is missing', function (): void {
        $exception = ViteConfigurationException::empty(
            'vite.entry',
            'Set vite.entry in config/vite.php.',
        );

        $vite = $this->createMock(Vite::class);
        $vite->method('headTags')->willThrowException($exception);

        $extension = new ViteExtension($vite, 'resources/js/app.ts');

        expect(fn () => $extension->vite())->toThrow(ViteConfigurationException::class);
    });

    it('integrates with a Latte engine fixture and renders an inline vite() call to the manifest tags', function (): void {
        $basePath = sys_get_temp_dir() . '/markommerce-vite-ext-test-' . bin2hex(random_bytes(8));
        $manifestDirectory = $basePath . '/public/build/.vite';
        mkdir($manifestDirectory, 0755, true);

        $manifest = [
            'resources/js/app.ts' => [
                'file' => 'assets/app.abc123.js',
                'css' => ['assets/app.def456.css'],
            ],
        ];
        file_put_contents($manifestDirectory . '/manifest.json', json_encode($manifest));

        $config = new ConfigRepository([
            'vite' => [
                'entry' => 'resources/js/app.ts',
                'buildDirectory' => 'build',
                'manifestFilename' => '.vite/manifest.json',
                'useDevServer' => false,
            ],
        ]);

        $vite = new Vite($config, new ProjectPaths($basePath));
        $extension = new ViteExtension($vite, 'resources/js/app.ts');

        $cacheDir = sys_get_temp_dir() . '/latte-vite-ext-cache-' . bin2hex(random_bytes(8));
        mkdir($cacheDir, 0755, true);

        $engine = new Engine();
        $engine->setTempDirectory($cacheDir);
        $engine->addExtension($extension);

        $templatePath = $cacheDir . '/vite-test.latte';
        file_put_contents($templatePath, '{vite()}');

        $html = $engine->renderToString($templatePath);

        expect($html)->toContain('assets/app.abc123.js');
        expect($html)->toContain('assets/app.def456.css');
        expect($html)->toContain('<script type="module"');
        expect($html)->toContain('<link rel="stylesheet"');

        array_map('unlink', glob($cacheDir . '/*') ?: []);
        rmdir($cacheDir);
        array_map('unlink', glob($manifestDirectory . '/*') ?: []);
        rmdir($manifestDirectory);
        rmdir(dirname($manifestDirectory));
        rmdir(dirname(dirname($manifestDirectory)));
        rmdir($basePath);
    });
});
