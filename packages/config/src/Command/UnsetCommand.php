<?php

declare(strict_types=1);

namespace Markommerce\Config\Command;

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Markommerce\Config\Contracts\ConfigWriterInterface;
use Markommerce\Config\Exceptions\AxisNotDeclaredException;
use Markommerce\Config\Exceptions\ConfigNotFoundException;
use Markommerce\Config\Exceptions\StaleConfigWriteException;
use Markommerce\Config\Registry\ConfigRegistry;
use Markommerce\Scope\Signature\ScopeSignature;

/** @noinspection PhpUnused */
#[Command(name: 'config:unset', description: 'Unset a config value globally or for a specific scope')]
readonly class UnsetCommand implements CommandInterface
{
    public function __construct(
        private ConfigRegistry $registry,
        private ConfigWriterInterface $writer,
    ) {}

    /**
     * @throws ConfigNotFoundException|StaleConfigWriteException|AxisNotDeclaredException
     */
    public function execute(
        Input $input,
        Output $output,
    ): int {
        $key = $input->getArgument(0);

        if ($key === null) {
            $output->writeLine('Usage: config:unset <key> [--scope=axis=value,...]');

            return 1;
        }

        // Validate key exists in registry
        try {
            $this->registry->byKey($key);
        } catch (ConfigNotFoundException $e) {
            $output->writeLine($e->getMessage());

            return 1;
        }

        $scopeOption = $input->getOption('scope');

        try {
            if ($scopeOption === null) {
                $this->writer->unsetGlobal($key);
            } else {
                $signature = $this->parseScope($scopeOption);
                if ($signature === null) {
                    $output->writeLine(
                        "Invalid --scope format '$scopeOption'. Expected: axis=value,axis2=value2",
                    );

                    return 1;
                }

                $this->writer->unsetOverride($key, $signature);
            }
        } catch (StaleConfigWriteException $e) {
            $output->writeLine($e->getMessage());

            return 1;
        } catch (AxisNotDeclaredException $e) {
            $definition = $this->registry->byKey($key);
            $declared = implode(', ', $definition->axes);
            $output->writeLine($e->getMessage());
            $output->writeLine(
                "Declared axes for '$key': " . ($declared !== '' ? $declared : '(none)'),
            );

            return 1;
        }

        $output->writeLine("Config '$key' unset successfully.");

        return 0;
    }

    private function parseScope(string $scopeOption): ?ScopeSignature
    {
        $pairs = explode(',', $scopeOption);

        /** @var array<string, string> $axisValues */
        $axisValues = [];

        foreach ($pairs as $pair) {
            $parts = explode('=', $pair, 2);
            if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
                return null;
            }

            $axisValues[$parts[0]] = $parts[1];
        }

        return new ScopeSignature($axisValues);
    }
}
