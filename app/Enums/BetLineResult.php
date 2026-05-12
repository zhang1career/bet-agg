<?php

declare(strict_types=1);

namespace App\Enums;

use App\Contracts\HasDictionaryLabel;

enum BetLineResult: int implements HasDictionaryLabel
{
    case Pending = 0;
    case Win = 1;
    case Lose = 2;
    case Void = 3;

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'pending',
            self::Win => 'win',
            self::Lose => 'lose',
            self::Void => 'void',
        };
    }
}
