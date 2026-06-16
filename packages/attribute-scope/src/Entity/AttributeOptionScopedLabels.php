<?php

declare(strict_types=1);

namespace Markommerce\AttributeScope\Entity;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;
use Markommerce\Attribute\Entity\AttributeOption;
use Markommerce\Scope\Exceptions\ScopeStorageException;
use Markommerce\Scope\Storage\DefaultScopeGuard;
use Markommerce\Scope\Storage\HasScopesInterface;

#[Table(extends: AttributeOption::class)]
class AttributeOptionScopedLabels extends Entity implements HasScopesInterface
{
    /** @var array<string, array<string, mixed>>|null */
    #[Column(name: 'scoped_labels', type: 'json', nullable: true)]
    public ?array $scopedLabels = null;

    /**
     * @throws ScopeStorageException
     */
    public function setOverride(
        string $signature,
        string $property,
        mixed $value,
    ): void {
        DefaultScopeGuard::assertWritable($signature);
        $scopedLabels = $this->scopedLabels ?? [];
        $scopedLabels[$signature][$property] = $value;
        ksort($scopedLabels[$signature]);
        ksort($scopedLabels);
        $this->scopedLabels = $scopedLabels;
    }

    public function override(
        string $signature,
        string $property,
    ): mixed {
        return $this->scopedLabels[$signature][$property] ?? null;
    }

    public function hasOverride(
        string $signature,
        string $property,
    ): bool {
        return array_key_exists($signature, $this->scopedLabels ?? [])
            && array_key_exists($property, $this->scopedLabels[$signature]);
    }

    /**
     * @throws ScopeStorageException
     */
    public function clearOverride(
        string $signature,
        string $property,
    ): void {
        DefaultScopeGuard::assertWritable($signature);
        if (!isset($this->scopedLabels[$signature])) {
            return;
        }

        $scopedLabels = $this->scopedLabels;
        unset($scopedLabels[$signature][$property]);

        if ($scopedLabels[$signature] === []) {
            unset($scopedLabels[$signature]);
        }

        ksort($scopedLabels);
        $this->scopedLabels = $scopedLabels ?: null;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function overrides(): array
    {
        return $this->scopedLabels ?? [];
    }
}
