<?php

declare(strict_types=1);

namespace Markommerce\Scope\Storage;

use Marko\Database\Attributes\Column;
use Marko\Database\Entity\Entity;

/**
 * Provides scope override storage for entities.
 *
 * Usage constraints:
 * - Consumers MUST declare `implements HasScopesInterface` on their class because PHP does not
 *   allow a trait to enforce interface implementation (`trait T implements I` is not valid PHP).
 * - This trait is intended for use on {@see Entity} subclasses so that
 *   the {@see Column} attribute on `$scopes` is picked up by `EntityMetadataFactory` for schema
 *   generation and hydration.
 *
 * @phpstan-ignore trait.unused
 */
trait HasScopes
{
    #[Column(name: 'scopes', type: 'json', nullable: true)]
    public ?array $scopes = null;

    public function setOverride(
        string $signature,
        string $property,
        mixed $value,
    ): void {
        $scopes = $this->scopes ?? [];
        $scopes[$signature][$property] = $value;
        ksort($scopes[$signature]);
        ksort($scopes);
        $this->scopes = $scopes;
    }

    public function override(
        string $signature,
        string $property,
    ): mixed {
        return $this->scopes[$signature][$property] ?? null;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function overrides(): array
    {
        return $this->scopes ?? [];
    }

    public function hasOverride(
        string $signature,
        string $property,
    ): bool {
        return array_key_exists($signature, $this->scopes ?? [])
            && array_key_exists($property, $this->scopes[$signature]);
    }

    public function clearOverride(
        string $signature,
        string $property,
    ): void {
        if (!isset($this->scopes[$signature])) {
            return;
        }

        $scopes = $this->scopes;
        unset($scopes[$signature][$property]);

        if ($scopes[$signature] === []) {
            unset($scopes[$signature]);
        }

        ksort($scopes);
        $this->scopes = $scopes ?: null;
    }
}
