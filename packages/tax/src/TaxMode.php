<?php

declare(strict_types=1);

namespace Markommerce\Tax;

enum TaxMode
{
    case Inclusive;
    case Exclusive;
}
