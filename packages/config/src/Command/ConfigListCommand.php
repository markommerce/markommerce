<?php

declare(strict_types=1);

namespace Markommerce\Config\Command;

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Markommerce\Config\Registry\ConfigRegistry;

#[Command(name: 'config:list', description: 'List all registered config keys')]
readonly class ConfigListCommand implements CommandInterface
{
    public function __construct(
        private ConfigRegistry $configRegistry,
    ) {}

    public function execute(
        Input $input,
        Output $output,
    ): int {
        $definitions = $this->configRegistry->all();

        if ($input->getOption('format') === 'json') {
            $rows = [];

            foreach ($definitions as $definition) {
                $rows[] = [
                    'key'    => $definition->key,
                    'source' => $definition->configClass,
                    'secret' => $definition->secret,
                ];
            }

            $output->writeLine((string) json_encode($rows, JSON_PRETTY_PRINT));

            return 0;
        }

        if (empty($definitions)) {
            $output->writeLine('No config keys registered.');

            return 0;
        }

        foreach ($definitions as $definition) {
            $secret = $definition->secret ? ' [secret]' : '';

            $output->writeLine(sprintf(
                '%s  %s%s',
                $definition->key,
                $definition->configClass,
                $secret,
            ));
        }

        return 0;
    }
}
