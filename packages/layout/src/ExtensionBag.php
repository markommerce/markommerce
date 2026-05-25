<?php

declare(strict_types=1);

namespace Markommerce\Layout;

use Markommerce\Layout\Contracts\ExtensionAttribute;
use Markommerce\Layout\Exceptions\DuplicateExtensionException;

/**
 * Immutable, typed collection of extension attributes keyed by class-string.
 *
 * Extensions are stored and retrieved by their exact class name. Each class
 * may only appear once — use withExtension on ExtensibleData to build up
 * the bag incrementally.
 */
readonly class ExtensionBag
{
    /**
     * @param array<class-string<ExtensionAttribute>, ExtensionAttribute> $extensions
     */
    public function __construct(
        private array $extensions = [],
    ) {}

    /**
     * Retrieve an extension by its class-string.
     *
     * @template T of ExtensionAttribute
     * @param class-string<T> $class
     * @return T|null
     * @phpstan-return T|null
     */
    public function get(string $class): ?ExtensionAttribute
    {
        /** @phpstan-var T|null */
        return $this->extensions[$class] ?? null;
    }

    /**
     * Return a new bag with the given extension added.
     *
     * @throws DuplicateExtensionException
     */
    public function with(ExtensionAttribute $extension): self
    {
        $class = $extension::class;

        if (isset($this->extensions[$class])) {
            throw DuplicateExtensionException::forClass($class);
        }

        return new self(array_merge($this->extensions, [$class => $extension]));
    }
}
