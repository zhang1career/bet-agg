<?php

declare(strict_types=1);

namespace App\Enums;

use App\Contracts\HasDictionaryLabel;

enum GameStatus: int implements HasDictionaryLabel
{
    /** Betting allowed on open markets under this game. */
    case Open = 1;
    /** Betting disabled (no new stakes); distinct from suspended single markets. */
    case Closed = 2;
    /** Game outcome recorded; tied to settlement workflow. */
    case Settled = 3;

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Closed => 'Closed',
            self::Settled => 'Settled',
        };
    }
}
