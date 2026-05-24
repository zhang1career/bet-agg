<?php

declare(strict_types=1);

namespace App\Enums;

use App\Contracts\HasDictionaryLabel;

enum MarketType: int implements HasDictionaryLabel
{
    /** 胜平负（程序 synthetic 三个 outcome）；持久化 {@code biz_market.type} = 0 */
    case OneX2 = 0;

    public function label(): string
    {
        return match ($this) {
            self::OneX2 => '1X2',
        };
    }
}
