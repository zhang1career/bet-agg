<?php

declare(strict_types=1);

namespace App\Enums;

enum SportMarketType: int
{
    /** Unset or legacy row not mapped to a known type. */
    case Unknown = 0;
    /** Full-time 1X2 (home / draw / away). */
    case MatchResult1x2 = 1;

    public function label(): string
    {
        return match ($this) {
            self::Unknown => 'Unknown',
            self::MatchResult1x2 => '1X2 (full-time result)',
        };
    }
}
