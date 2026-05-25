<?php

declare(strict_types=1);

namespace Markommerce\Layout\Command;

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Markommerce\Layout\Cache\ArtifactWriterInterface;
use Markommerce\Layout\Compiler\CompilerInterface;
use Markommerce\Layout\Exceptions\LayoutException;

/** @noinspection PhpUnused */
#[Command(name: 'layout:compile', description: 'Compile all discovered layouts into the artifact cache file')]
readonly class CompileCommand implements CommandInterface
{
    public function __construct(
        private CompilerInterface $compiler,
        private ArtifactWriterInterface $artifactWriter,
    ) {}

    /**
     * @throws LayoutException
     */
    public function execute(
        Input $input,
        Output $output,
    ): int {
        try {
            $trees = $this->compiler->compile();
        } catch (LayoutException $e) {
            $output->writeLine('[Layout Compile Error]');
            $output->writeLine('Message: ' . $e->getMessage());
            $output->writeLine('Context: ' . $e->getContext());
            $output->writeLine('Suggestion: ' . $e->getSuggestion());

            return 1;
        }

        $this->artifactWriter->write($trees);

        $handleCount = count($trees);
        $artifactPath = $this->artifactWriter->getPath();

        $output->writeLine("Layout compile complete: $handleCount handle(s) compiled.");
        $output->writeLine("Artifact written to: $artifactPath");

        return 0;
    }
}
