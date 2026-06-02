<?php

declare(strict_types=1);

namespace Markommerce\Config\Command;

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\Core\Container\PreferenceRegistry;
use Markommerce\Config\Casting\ValueCaster;
use Markommerce\Config\ConfigResolver;
use Markommerce\Config\Contracts\ConfigStorageInterface;
use Markommerce\Config\Contracts\SecretCipherInterface;
use Markommerce\Config\Encryption\NullSecretCipher;
use Markommerce\Config\Exceptions\ConfigNotFoundException;
use Markommerce\Config\Proxy\ProxyLocator;
use Markommerce\Config\Registry\ConfigRegistry;

#[Command(name: 'config:get', description: 'Get a config value by key')]
readonly class ConfigGetCommand implements CommandInterface
{
    private const string REDACTED = '***';

    public function __construct(
        private ConfigRegistry $configRegistry,
        private ConfigStorageInterface $configStorage,
        private SecretCipherInterface $secretCipher = new NullSecretCipher(),
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
            return $this->handleNotFound($key, $output);
        }

        $valueCaster = new ValueCaster();
        $proxyLocator = new ProxyLocator();
        $preferenceRegistry = new PreferenceRegistry();

        $resolver = new ConfigResolver(
            configRegistry: $this->configRegistry,
            configStorage: $this->configStorage,
            valueCaster: $valueCaster,
            secretCipher: $this->secretCipher,
            proxyLocator: $proxyLocator,
            preferenceRegistry: $preferenceRegistry,
        );

        if ($definition->secret) {
            $output->writeLine(self::REDACTED);

            return 0;
        }

        $value = $resolver->resolved($definition->configClass, $definition->field);
        $output->writeLine((string) $value);

        return 0;
    }

    private function handleNotFound(
        string $key,
        Output $output,
    ): int {
        $output->writeLine("Error: Config key '$key' not found.");

        $allKeys = array_map(
            fn ($def) => $def->key,
            $this->configRegistry->all(),
        );

        $suggestions = $this->closestKeys($key, $allKeys);

        if (!empty($suggestions)) {
            $output->writeLine('Did you mean one of these?');

            foreach ($suggestions as $suggestion) {
                $output->writeLine("  $suggestion");
            }
        }

        return 1;
    }

    /**
     * @param list<string> $allKeys
     * @return list<string>
     */
    private function closestKeys(
        string $key,
        array $allKeys,
    ): array {
        $distances = [];

        foreach ($allKeys as $candidate) {
            $distances[$candidate] = levenshtein($key, $candidate);
        }

        asort($distances);

        return array_slice(array_keys($distances), 0, 3);
    }
}
