<?php

declare(strict_types=1);

namespace Markommerce\CatalogPriceIndex\Command;

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Markommerce\CatalogPriceIndex\Contracts\PriceIndexerInterface;

#[Command(name: 'catalog:price-index:rebuild', description: 'Rebuild the full product price index')]
readonly class RebuildPriceIndexCommand implements CommandInterface
{
    public function __construct(private PriceIndexerInterface $priceIndexer) {}

    public function execute(
        Input $input,
        Output $output,
    ): int
    {
        $chunkOption = $input->getOption('chunk');
        $chunkSize   = $chunkOption !== null ? (int) $chunkOption : 500;

        if ($chunkSize < 1) {
            $chunkSize = 500;
        }

        /** @var positive-int $chunkSize */
        $count = $this->priceIndexer->rebuildAll($chunkSize);
        $output->writeLine(sprintf('Rebuilt %d price index entries.', $count));

        return 0;
    }
}
