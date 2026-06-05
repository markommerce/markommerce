<?php

declare(strict_types=1);

namespace Markommerce\Criteria\Sort;

use Markommerce\Criteria\Exceptions\EmptySortException;

readonly class Sort
{
    /** @var list<SortField> */
    public array $fields;

    /**
     * @throws EmptySortException
     */
    public function __construct(SortField ...$fields)
    {
        if ($fields === []) {
            throw EmptySortException::create();
        }

        $this->fields = array_values($fields);
    }
}
