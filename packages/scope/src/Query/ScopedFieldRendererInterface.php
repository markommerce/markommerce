<?php

declare(strict_types=1);

namespace Markommerce\Scope\Query;

use Marko\Database\Exceptions\InvalidColumnException;

interface ScopedFieldRendererInterface
{
    /**
     * @throws InvalidColumnException When an identifier in the expression is invalid
     */
    public function render(ScopedFieldExpression $expression): string;
}
