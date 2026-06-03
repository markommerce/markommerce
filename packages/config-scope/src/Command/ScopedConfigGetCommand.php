<?php

declare(strict_types=1);

namespace Markommerce\ConfigScope\Command;

use Marko\Core\Attributes\Preference;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Markommerce\Config\Command\ConfigGetCommand;
use Markommerce\Config\Exceptions\ConfigNotFoundException;
use Markommerce\Config\Registry\ConfigRegistry;
use Markommerce\ConfigScope\ScopedConfigResolver;
use Markommerce\Scope\Context\ScopeContext;
use Throwable;

/** @noinspection PhpUnused */
#[Preference(replaces: ConfigGetCommand::class)]
readonly class ScopedConfigGetCommand implements CommandInterface
{
    private const string REDACTED = '***';

    public function __construct(
        private ConfigRegistry $configRegistry,
        private ScopedConfigResolver $resolver,
        private ScopeContext $scopeContext,
    ) {}

    /**
     * @throws ConfigNotFoundException
     */
    public function execute(
        Input $input,
        Output $output,
    ): int {
        $key = $input->getArgument(0);

        if ($key === null) {
            $output->writeLine('Error: Missing required argument <key>.');
            $output->writeLine('Usage: config:get <key>');

            return 1;
        }

        try {
            $definition = $this->configRegistry->byKey($key);
        } catch (ConfigNotFoundException) {
            $output->writeLine("Error: Config key '$key' not found.");

            return 1;
        }

        if ($definition->secret) {
            $output->writeLine(self::REDACTED);

            return 0;
        }

        $scopeOption = $input->getOption('scope');

        if ($scopeOption !== null) {
            return $this->executeScoped($definition->configClass, $definition->field, $scopeOption, $output);
        }

        $value = $this->resolver->resolved($definition->configClass, $definition->field);
        $output->writeLine((string) $value);

        return 0;
    }

    /**
     * @param class-string $configClass
     */
    private function executeScoped(
        string $configClass,
        string $field,
        string $scopeOption,
        Output $output,
    ): int {
        $pairs = explode(',', $scopeOption);

        foreach ($pairs as $pair) {
            $segments = explode('=', $pair, 2);
            $axis = $segments[0];
            $path = $segments[1] ?? '';

            try {
                $this->scopeContext->in($axis, $path);
            } catch (Throwable $e) {
                $this->scopeContext->clearAll();
                $output->writeLine('Error: ' . $e->getMessage());

                return 1;
            }
        }

        try {
            $value = $this->resolver->resolvedAt($configClass, $field, $this->scopeContext);
        } finally {
            $this->scopeContext->clearAll();
        }

        $output->writeLine((string) $value);

        return 0;
    }
}
