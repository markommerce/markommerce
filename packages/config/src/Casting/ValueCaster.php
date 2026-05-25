<?php

declare(strict_types=1);

namespace Markommerce\Config\Casting;

use Markommerce\Config\Exceptions\InvalidConfigValueException;
use Markommerce\Config\ValueObjects\ConfigDefinition;

class ValueCaster
{
    /**
     * @throws InvalidConfigValueException
     */
    public function cast(
        mixed $rawValue,
        ConfigDefinition $definition,
    ): mixed
    {
        $type = $definition->type;

        if ($type === 'string') {
            if (!is_string($rawValue)) {
                throw InvalidConfigValueException::forKey($definition->key, (string) $rawValue, $type);
            }

            return $rawValue;
        }

        if ($type === 'int') {
            if (!is_int($rawValue)) {
                throw InvalidConfigValueException::forKey($definition->key, (string) $rawValue, $type);
            }

            return $rawValue;
        }

        if ($type === 'float') {
            if (is_int($rawValue)) {
                return (float) $rawValue;
            }

            if (!is_float($rawValue)) {
                throw InvalidConfigValueException::forKey($definition->key, (string) $rawValue, $type);
            }

            return $rawValue;
        }

        if ($type === 'bool') {
            if (!is_bool($rawValue)) {
                throw InvalidConfigValueException::forKey($definition->key, (string) $rawValue, $type);
            }

            return $rawValue;
        }

        if ($type === 'array') {
            if (!is_array($rawValue)) {
                throw InvalidConfigValueException::forKey($definition->key, (string) $rawValue, $type);
            }

            return $rawValue;
        }

        if (is_subclass_of($type, \BackedEnum::class)) {
            $result = $type::tryFrom($rawValue);

            if ($result === null) {
                throw InvalidConfigValueException::forKey($definition->key, (string) $rawValue, $type);
            }

            return $result;
        }

        return $rawValue;
    }
}
