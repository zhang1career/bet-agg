<?php

declare(strict_types=1);

namespace App\Enums;

use App\Contracts\HasDictionaryLabel;

enum MarketStatus: int implements HasDictionaryLabel
{
    /** Lines accepted for this market. */
    case Open = 1;
    /** Temporarily not accepting bets (shown odds may be stale). */
    case Suspended = 2;
    /** Outcome fixed; typically selections/game settle together. */
    case Settled = 3;

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Suspended => 'Suspended',
            self::Settled => 'Settled',
        };
    }
}
