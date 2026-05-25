<?php

declare(strict_types=1);

namespace Markommerce\Config;

use Markommerce\Config\Contracts\ConfigStorageInterface;
use Markommerce\Config\Contracts\ConfigWriterInterface;
use Markommerce\Config\Contracts\SecretCipherInterface;
use Markommerce\Config\Exceptions\AxisNotDeclaredException;
use Markommerce\Config\Exceptions\ConfigNotFoundException;
use Markommerce\Config\Exceptions\SecretCipherException;
use Markommerce\Config\Exceptions\StaleConfigWriteException;
use Markommerce\Config\Registry\ConfigRegistry;
use Markommerce\Config\ValueObjects\ConfigRow;
use Markommerce\Scope\Signature\ScopeSignature;

class ConfigWriter implements ConfigWriterInterface
{
    private const int MAX_RETRIES = 3;

    public function __construct(
        private ConfigRegistry $registry,
        private ConfigStorageInterface $storage,
        private SecretCipherInterface $cipher,
    ) {}

    /**
     * @throws ConfigNotFoundException|StaleConfigWriteException|SecretCipherException
     */
    public function setGlobal(
        string $key,
        mixed $value,
    ): void {
        $definition = $this->registry->byKey($key);

        if ($definition->secret && $value !== null) {
            $value = $this->cipher->encrypt(json_encode($value, JSON_THROW_ON_ERROR));
        }

        $this->writeWithRetry($key, static function (ConfigRow $row) use ($value): ConfigRow {
            if ($value === null) {
                return $row->withoutGlobal();
            }

            return $row->withGlobal($value);
        });
    }

    /**
     * @throws ConfigNotFoundException|StaleConfigWriteException
     */
    public function unsetGlobal(string $key): void
    {
        $this->setGlobal($key, null);
    }

    /**
     * @throws ConfigNotFoundException|AxisNotDeclaredException|StaleConfigWriteException|SecretCipherException
     */
    public function setOverride(
        string $key,
        ScopeSignature $signature,
        mixed $value,
    ): void {
        $definition = $this->registry->byKey($key);

        foreach ($signature->axes() as $axis) {
            if (!in_array($axis, $definition->axes, true)) {
                throw AxisNotDeclaredException::forPropertyAndAxis($key, $axis);
            }
        }

        if ($definition->secret && $value !== null) {
            $value = $this->cipher->encrypt(json_encode($value, JSON_THROW_ON_ERROR));
        }

        $this->writeWithRetry($key, static function (ConfigRow $row) use ($signature, $value): ConfigRow {
            if ($value === null) {
                return $row->withoutOverride($signature->toString());
            }

            return $row->withOverride($signature->toString(), $value);
        });
    }

    /**
     * @throws ConfigNotFoundException|AxisNotDeclaredException|StaleConfigWriteException
     */
    public function unsetOverride(
        string $key,
        ScopeSignature $signature,
    ): void {
        $this->setOverride($key, $signature, null);
    }

    /**
     * @param callable(ConfigRow): ConfigRow $mutate
     *
     * @throws StaleConfigWriteException
     */
    private function writeWithRetry(
        string $key,
        callable $mutate,
    ): void {
        for ($attempt = 0; $attempt < self::MAX_RETRIES; $attempt++) {
            $existing = $this->storage->load($key);
            $currentVersion = $existing !== null ? $existing->version : 0;
            $row = $existing ?? new ConfigRow(key: $key, value: null, overrides: [], version: 0);

            $mutated = $mutate($row);

            if ($this->storage->compareAndSave($key, $mutated, $currentVersion)) {
                return;
            }
        }

        throw StaleConfigWriteException::afterRetries($key, self::MAX_RETRIES);
    }
}
