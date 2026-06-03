<?php

declare(strict_types=1);

namespace Markommerce\Config\Command;

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Markommerce\Config\Contracts\ConfigWriterInterface;
use Markommerce\Config\Exceptions\ConfigNotFoundException;
use Markommerce\Config\Exceptions\StaleConfigWriteException;
use Markommerce\Config\Registry\ConfigRegistry;

/** @noinspection PhpUnused */
#[Command(name: 'config:unset', description: 'Unset a config value globally or for a specific scope')]
readonly class UnsetCommand implements CommandInterface
{
    public function __construct(
        private ConfigRegistry $registry,
        private ConfigWriterInterface $writer,
    ) {}

    /**
     * @throws ConfigNotFoundException|StaleConfigWriteException
     */
    public function execute(
        Input $input,
        Output $output,
    ): int {
        $key = $input->getArgument(0);

        if ($key === null) {
            $output->writeLine('Usage: config:unset <key>');

            return 1;
        }

        // Validate key exists in registry
        try {
            $this->registry->byKey($key);
        } catch (ConfigNotFoundException $e) {
            $output->writeLine($e->getMessage());

            return 1;
        }

        try {
            $this->writer->unsetGlobal($key);
        } catch (StaleConfigWriteException $e) {
            $output->writeLine($e->getMessage());

            return 1;
        }

        $output->writeLine("Config '$key' unset successfully.");

        return 0;
    }
}
