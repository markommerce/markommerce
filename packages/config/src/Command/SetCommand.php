<?php

declare(strict_types=1);

namespace Markommerce\Config\Command;

use BackedEnum;
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
#[Command(name: 'config:set', description: 'Set a config value globally or for a specific scope')]
readonly class SetCommand implements CommandInterface
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
        $rawValue = $input->getArgument(1);

        if ($key === null || $rawValue === null) {
            $output->writeLine('Usage: config:set <key> <value> [--scope=axis=value,...]');

            return 1;
        }

        try {
            $definition = $this->registry->byKey($key);
        } catch (ConfigNotFoundException $e) {
            $output->writeLine($e->getMessage());

            return 1;
        }

        $parsed = $this->parseValue($rawValue, $definition->type);

        if ($parsed === null && $rawValue !== 'null') {
            $output->writeLine(
                "Cannot parse value '$rawValue' as type '$definition->type'.",
            );

            return 1;
        }

        $scopeOption = $input->getOption('scope');

        try {
            if ($scopeOption === null) {
                $this->writer->setGlobal($key, $parsed);
            } else {
                $signature = $this->parseScope($scopeOption);
                if ($signature === null) {
                    $output->writeLine(
                        "Invalid --scope format '$scopeOption'. Expected: axis=value,axis2=value2",
                    );

                    return 1;
                }

                $this->writer->setOverride($key, $signature, $parsed);
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

        $output->writeLine("Config '$key' set successfully.");

        return 0;
    }

    private function parseValue(
        string $raw,
        string $type,
    ): mixed {
        return match (true) {
            $type === 'string' => $raw,
            $type === 'int' => $this->parseInt($raw),
            $type === 'float' => $this->parseFloat($raw),
            $type === 'bool' => $this->parseBool($raw),
            $type === 'array' => $this->parseArray($raw),
            is_subclass_of($type, BackedEnum::class) => $type::tryFrom($raw),
            default => $raw,
        };
    }

    private function parseInt(string $raw): ?int
    {
        $result = filter_var($raw, FILTER_VALIDATE_INT);

        return $result !== false ? $result : null;
    }

    private function parseFloat(string $raw): ?float
    {
        $result = filter_var($raw, FILTER_VALIDATE_FLOAT);

        return $result !== false ? $result : null;
    }

    private function parseBool(string $raw): ?bool
    {
        return match (strtolower($raw)) {
            'true', '1', 'yes' => true,
            'false', '0', 'no' => false,
            default => null,
        };
    }

    /**
     * @return array<mixed>|null
     */
    private function parseArray(string $raw): ?array
    {
        $decoded = json_decode($raw, true);

        if (!is_array($decoded)) {
            return null;
        }

        return $decoded;
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
