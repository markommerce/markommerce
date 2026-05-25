<?php

declare(strict_types=1);

namespace Markommerce\Layout\Compiler;

use Markommerce\Layout\Contracts\DecoratorInterface;
use Markommerce\Layout\Exceptions\MissingSlotInnerException;

class DecoratorTemplateValidator
{
    public const string SLOT_INNER_PLACEHOLDER = '{slot inner}';

    /**
     * Validates that a decorator class template contains the required `{slot inner}` placeholder.
     * This check runs at compile-time (when a WrapWith operation is resolved) so that missing
     * placeholders surface during `layout:compile`, not at render time.
     *
     * @param class-string<DecoratorInterface> $decoratorClass
     *
     * @throws MissingSlotInnerException
     */
    public function validate(string $decoratorClass): void
    {
        /** @var DecoratorInterface $decorator */
        $decorator = new $decoratorClass();
        $template = $decorator->template();

        if (!str_contains($template, self::SLOT_INNER_PLACEHOLDER)) {
            throw MissingSlotInnerException::forDecorator($decoratorClass);
        }
    }
}
