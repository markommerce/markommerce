<?php

declare(strict_types=1);

namespace Markommerce\Money;

use Brick\Math\RoundingMode as BrickRoundingMode;

enum RoundingMode
{
    case HalfUp;
    case HalfDown;
    case HalfEven;
    case Up;
    case Down;
    case Ceiling;
    case Floor;
    case Unnecessary;

    public function toBrick(): BrickRoundingMode
    {
        return match ($this) {
            self::HalfUp => BrickRoundingMode::HALF_UP,
            self::HalfDown => BrickRoundingMode::HALF_DOWN,
            self::HalfEven => BrickRoundingMode::HALF_EVEN,
            self::Up => BrickRoundingMode::UP,
            self::Down => BrickRoundingMode::DOWN,
            self::Ceiling => BrickRoundingMode::CEILING,
            self::Floor => BrickRoundingMode::FLOOR,
            self::Unnecessary => BrickRoundingMode::UNNECESSARY,
        };
    }
}
