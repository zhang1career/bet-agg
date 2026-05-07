<?php

declare(strict_types=1);

namespace App\Enums;

enum MarketType: int
{
    /** 胜平负（程序 synthetic 三个 outcome） */
    case OneX2 = 1;

    public function label(): string
    {
        return match ($this) {
            self::OneX2 => '胜平负',
        };
    }
}
