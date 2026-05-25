<?php

declare(strict_types=1);

namespace Markommerce\Config\Command;

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Markommerce\Config\Exceptions\InvalidConfigClassException;
use Markommerce\Config\Proxy\PreferenceAwareScanner;
use Markommerce\Config\Proxy\ProxyGenerator;
use Markommerce\Config\Proxy\ProxyLocator;
use Markommerce\Config\Proxy\ProxyWriter;
use Markommerce\Config\Registry\ConfigRegistry;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

#[Command(name: 'config:generate', description: 'Generate proxy classes for all registered config classes')]
readonly class GenerateCommand implements CommandInterface
{
    public function __construct(
        private ConfigRegistry $configRegistry,
        private PreferenceAwareScanner $preferenceAwareScanner,
        private ProxyGenerator $proxyGenerator,
        private ProxyWriter $proxyWriter,
        private ProxyLocator $proxyLocator,
        private string $targetDir,
    ) {}

    /**
     * @throws InvalidConfigClassException
     */
    public function execute(
        Input $input,
        Output $output,
    ): int {
        $definitions = $this->configRegistry->all();

        if (empty($definitions)) {
            $output->writeLine('No config classes registered. Nothing to generate.');

            return 0;
        }

        $this->clearTargetDirectory($this->targetDir);

        /** @var list<class-string> $configClasses */
        $configClasses = array_values(array_unique(
            array_map(fn ($def) => $def->configClass, $definitions),
        ));

        try {
            $expandedClasses = $this->preferenceAwareScanner->expand($configClasses);
        } catch (InvalidConfigClassException $e) {
            $output->writeLine('ERROR: ' . $e->getMessage());

            return 1;
        }

        $count = 0;

        foreach ($expandedClasses as $configClass) {
            $classDefinitions = array_values(array_filter(
                $definitions,
                fn ($def) => $def->configClass === $configClass,
            ));

            // For preference subclasses not directly in the registry, use the parent's definitions
            if (empty($classDefinitions)) {
                $classDefinitions = $definitions;
            }

            try {
                $source = $this->proxyGenerator->generate($configClass, $classDefinitions);
            } catch (InvalidConfigClassException $e) {
                $output->writeLine('ERROR: ' . $e->getMessage());

                return 1;
            }

            $proxyFqn = $this->proxyLocator->proxyClassFor($configClass);
            $path = $this->proxyWriter->write($proxyFqn, $source, $this->targetDir);

            $output->writeLine('✓ ' . $proxyFqn . ' -> ' . $path);
            $count++;
        }

        $output->writeLine(sprintf('Generated %d proxies in %s', $count, $this->targetDir));

        return 0;
    }

    private function clearTargetDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        /** @var SplFileInfo $fileInfo */
        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isDir()) {
                rmdir($fileInfo->getRealPath());
            } else {
                unlink($fileInfo->getRealPath());
            }
        }
    }
}
