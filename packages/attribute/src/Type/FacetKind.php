<?php

declare(strict_types=1);

namespace Markommerce\Attribute\Type;

enum FacetKind
{
    case Term;
    case Range;
    case None;
}
