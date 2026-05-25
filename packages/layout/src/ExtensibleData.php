<?php

declare(strict_types=1);

namespace Markommerce\Layout;

use Markommerce\Layout\Contracts\ExtensionAttribute;
use Markommerce\Layout\Exceptions\DuplicateExtensionException;
use ReflectionClass;

/**
 * Abstract base class for component data DTOs that support typed extension attributes.
 *
 * Subclasses should be readonly and call parent::__construct($extensions) from
 * their own constructors.
 *
 * IMPORTANT: Component classes that expose a plugin-extensible data() method
 * MUST NOT be readonly classes. Marko's Plugin mechanism uses a concrete subclass
 * strategy for classes without interfaces, and PHP does not allow subclassing
 * readonly classes with new state. See tasks 013 and 017 for details.
 */
abstract readonly class ExtensibleData
{
    public function __construct(
        public ExtensionBag $extensions,
    ) {}

    /**
     * Return a new data object with the given extension added to the bag.
     *
     * Uses reflection to reconstruct the concrete class with the same constructor
     * arguments, replacing the extensions bag with an augmented copy.
     *
     * @throws DuplicateExtensionException
     */
    public function withExtension(ExtensionAttribute $extension): static
    {
        $rc = new ReflectionClass($this);
        $constructor = $rc->getConstructor();

        $args = [];

        if ($constructor !== null) {
            foreach ($constructor->getParameters() as $param) {
                $name = $param->getName();

                if ($name === 'extensions') {
                    $args[$name] = $this->extensions->with($extension);
                } elseif ($rc->hasProperty($name)) {
                    $args[$name] = $rc->getProperty($name)->getValue($this);
                } elseif ($param->isDefaultValueAvailable()) {
                    $args[$name] = $param->getDefaultValue();
                }
            }
        }

        /** @var static $new */
        $new = $rc->newInstance(...$args);

        return $new;
    }
}
