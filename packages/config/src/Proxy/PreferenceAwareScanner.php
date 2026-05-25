<?php

declare(strict_types=1);

namespace Markommerce\Config\Proxy;

use Marko\Core\Container\PreferenceRegistry;
use Marko\Core\Exceptions\PreferenceConflictException;
use Markommerce\Config\Exceptions\InvalidConfigClassException;

class PreferenceAwareScanner
{
    public function __construct(
        private readonly PreferenceRegistry $preferenceRegistry,
    ) {}

    /**
     * Expands the list of registered config classes by including any preferred subclasses.
     *
     * @param list<class-string> $configClasses
     * @return list<class-string>
     *
     * @throws InvalidConfigClassException When a preferred class is not a subclass of the original
     * @throws PreferenceConflictException When a circular preference chain is detected
     */
    public function expand(array $configClasses): array
    {
        $result = [];

        foreach ($configClasses as $original) {
            $result[$original] = true;

            $preferred = $this->preferenceRegistry->getPreference($original);

            if ($preferred !== null) {
                if (!is_subclass_of($preferred, $original)) {
                    throw InvalidConfigClassException::nonSubclassPreference($original, $preferred);
                }

                $result[$preferred] = true;
            }
        }

        /** @var list<class-string> */
        return array_keys($result);
    }
}
