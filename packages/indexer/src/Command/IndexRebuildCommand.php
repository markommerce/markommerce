<?php

declare(strict_types=1);

namespace Markommerce\Indexer\Command;

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Markommerce\Indexer\Exceptions\UnknownIndexException;
use Markommerce\Indexer\Registry\IndexerRegistry;

#[Command(name: 'index:rebuild', description: 'Rebuild one or all product indexes')]
readonly class IndexRebuildCommand implements CommandInterface
{
    public function __construct(private IndexerRegistry $indexerRegistry) {}

    /**
     * @throws UnknownIndexException
     */
    public function execute(
        Input $input,
        Output $output,
    ): int
    {
        $chunkOption = $input->getOption('chunk');
        $chunkSize = $chunkOption !== null ? (int) $chunkOption : 500;

        if ($chunkSize < 1) {
            $chunkSize = 500;
        }

        /** @var positive-int $chunkSize */
        $name = $input->getArgument(0);

        if ($name !== null) {
            $indexer = $this->indexerRegistry->get($name);
            $count = $indexer->rebuildAll($chunkSize);
            $output->writeLine(sprintf('  %s: %d rows rebuilt.', $name, $count));
            $output->writeLine(sprintf('Total: %d rows rebuilt.', $count));

            return 0;
        }

        $total = 0;

        foreach ($this->indexerRegistry->names() as $indexName) {
            $count = $this->indexerRegistry->get($indexName)->rebuildAll($chunkSize);
            $output->writeLine(sprintf('  %s: %d rows rebuilt.', $indexName, $count));
            $total += $count;
        }

        $output->writeLine(sprintf('Total: %d rows rebuilt.', $total));

        return 0;
    }
}
