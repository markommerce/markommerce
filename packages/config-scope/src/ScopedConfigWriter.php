<?php

declare(strict_types=1);

namespace Markommerce\ConfigScope;

use Marko\Core\Attributes\Preference;
use Markommerce\Config\ConfigWriter;
use Markommerce\Config\Contracts\ConfigStorageInterface;
use Markommerce\Config\Contracts\SecretCipherInterface;
use Markommerce\Config\Exceptions\ConfigNotFoundException;
use Markommerce\Config\Registry\ConfigRegistry;
use Markommerce\ConfigScope\Contracts\ScopedConfigStorageInterface;
use Markommerce\ConfigScope\Contracts\ScopedConfigWriterInterface;
use Markommerce\ConfigScope\Exceptions\AxisNotDeclaredException;
use Markommerce\Scope\Metadata\ScopedFieldRegistry;
use Markommerce\Scope\Signature\ScopeSignature;

#[Preference(replaces: ConfigWriter::class)]
class ScopedConfigWriter extends ConfigWriter implements ScopedConfigWriterInterface
{
    public function __construct(
        ConfigRegistry $registry,
        ConfigStorageInterface $storage,
        SecretCipherInterface $cipher,
        private ScopedConfigStorageInterface $scopedStorage,
        private ScopedFieldRegistry $scopedFieldRegistry,
    ) {
        parent::__construct($registry, $storage, $cipher);
    }

    /**
     * @throws ConfigNotFoundException|AxisNotDeclaredException
     */
    public function setOverride(
        string $key,
        ScopeSignature $signature,
        mixed $value,
    ): void
    {
        $definition = $this->registry->byKey($key);
        $axes = $this->scopedFieldRegistry->axesForProperty($definition->configClass, $definition->field);

        foreach ($signature->axes() as $axis) {
            if (!in_array($axis, $axes, true)) {
                throw AxisNotDeclaredException::forPropertyAndAxis($key, $axis);
            }
        }

        if ($definition->secret && $value !== null) {
            $value = $this->cipher->encrypt(json_encode($value, JSON_THROW_ON_ERROR));
        }

        if ($value === null) {
            $this->scopedStorage->deleteOverride($key, $signature->toString());

            return;
        }

        $this->scopedStorage->saveOverride($key, $signature->toString(), $value);
    }

    /**
     * @throws ConfigNotFoundException|AxisNotDeclaredException
     */
    public function unsetOverride(
        string $key,
        ScopeSignature $signature,
    ): void
    {
        $this->setOverride($key, $signature, null);
    }
}
