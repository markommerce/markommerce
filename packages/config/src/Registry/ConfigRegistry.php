<?php

declare(strict_types=1);

namespace Markommerce\Config\Registry;

use Markommerce\Config\Exceptions\ConfigNotFoundException;
use Markommerce\Config\ValueObjects\ConfigDefinition;

class ConfigRegistry
{
    /**
     * @var array<string, array<string, ConfigDefinition>>
     */
    private array $byClassAndField = [];

    /**
     * @var array<string, ConfigDefinition>
     */
    private array $byKeyMap = [];

    /**
     * @param list<ConfigDefinition> $definitions
     */
    public function __construct(array $definitions)
    {
        foreach ($definitions as $definition) {
            $this->byClassAndField[$definition->configClass][$definition->field] = $definition;
            $this->byKeyMap[$definition->key] = $definition;
        }
    }

    /**
     * @param class-string $configClass
     *
     * @throws ConfigNotFoundException
     */
    public function definition(
        string $configClass,
        string $field,
    ): ConfigDefinition {
        if (!isset($this->byClassAndField[$configClass][$field])) {
            throw ConfigNotFoundException::forKey("$configClass::$$field");
        }

        return $this->byClassAndField[$configClass][$field];
    }

    /**
     * @throws ConfigNotFoundException
     */
    public function byKey(string $key): ConfigDefinition
    {
        if (!isset($this->byKeyMap[$key])) {
            throw ConfigNotFoundException::forKey($key);
        }

        return $this->byKeyMap[$key];
    }

    /**
     * @return list<ConfigDefinition>
     */
    public function all(): array
    {
        $all = [];

        foreach ($this->byClassAndField as $fieldMap) {
            foreach ($fieldMap as $definition) {
                $all[] = $definition;
            }
        }

        return $all;
    }
}
