<?php

declare(strict_types=1);

namespace Markommerce\ConfigScope\Command;

use Marko\Core\Attributes\Preference;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Markommerce\Config\Command\UnsetCommand;
use Markommerce\Config\Exceptions\ConfigNotFoundException;
use Markommerce\Config\Exceptions\StaleConfigWriteException;
use Markommerce\Config\Registry\ConfigRegistry;
use Markommerce\ConfigScope\Contracts\ScopedConfigWriterInterface;
use Markommerce\ConfigScope\Exceptions\AxisNotDeclaredException;
use Markommerce\Scope\Exceptions\InvalidSignatureException;
use Markommerce\Scope\Signature\ScopeSignature;

/** @noinspection PhpUnused */
#[Preference(replaces: UnsetCommand::class)]
readonly class ScopedUnsetCommand implements CommandInterface
{
    public function __construct(
        private ConfigRegistry $registry,
        private ScopedConfigWriterInterface $writer,
    ) {}

    /**
     * @throws ConfigNotFoundException|StaleConfigWriteException|AxisNotDeclaredException|InvalidSignatureException
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

        try {
            $this->registry->byKey($key);
        } catch (ConfigNotFoundException $e) {
            $output->writeLine($e->getMessage());

            return 1;
        }

        $scopeOption = $input->getOption('scope');

        try {
            if ($scopeOption !== null) {
                $signature = $this->parseScopeSignature($scopeOption);
                $this->writer->unsetOverride($key, $signature);
            } else {
                $this->writer->unsetGlobal($key);
            }
        } catch (StaleConfigWriteException | AxisNotDeclaredException $e) {
            $output->writeLine($e->getMessage());

            return 1;
        }

        $output->writeLine("Config '$key' unset successfully.");

        return 0;
    }

    /**
     * @throws InvalidSignatureException
     */
    private function parseScopeSignature(string $scopeOption): ScopeSignature
    {
        $pairs = explode(',', $scopeOption);
        $axisValues = [];

        foreach ($pairs as $pair) {
            $segments = explode('=', $pair, 2);
            $axisValues[$segments[0]] = $segments[1] ?? '';
        }

        return new ScopeSignature($axisValues);
    }
}
