<?php

declare(strict_types=1);

namespace Markommerce\CatalogAttribute\Entity;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;
use Markommerce\Catalog\Entity\Product;

#[Table(extends: Product::class)]
class ProductAttributeValues extends Entity
{
    /** @var array<string, mixed>|null */
    #[Column(name: 'attribute_values', type: 'json', nullable: true)]
    public ?array $values = null;

    public function set(
        string $code,
        mixed $value,
    ): void
    {
        $values = $this->values ?? [];
        $values[$code] = $value;
        ksort($values);
        $this->values = $values;
    }

    public function get(string $code): mixed
    {
        return $this->values[$code] ?? null;
    }

    public function has(string $code): bool
    {
        return array_key_exists($code, $this->values ?? []);
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->values ?? [];
    }

    public function clear(string $code): void
    {
        if (!isset($this->values[$code])) {
            return;
        }

        $values = $this->values;
        unset($values[$code]);
        $this->values = $values !== [] ? $values : null;
    }
}
