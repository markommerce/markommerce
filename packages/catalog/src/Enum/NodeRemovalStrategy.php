<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Enum;

enum NodeRemovalStrategy
{
    case CASCADE;
    case PROMOTE_CHILDREN;
}
