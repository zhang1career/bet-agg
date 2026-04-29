<?php

declare(strict_types=1);

namespace App\Enums;

use App\Contracts\HasDictionaryLabel;

enum CheckoutPhase: int implements HasDictionaryLabel
{
    /** Draft slip; checkout not started */
    case None = 0;
    case AwaitPayment = 50;
    case Completed = 60;

    public function label(): string
    {
        return match ($this) {
            self::None => 'none',
            self::AwaitPayment => 'await payment',
            self::Completed => 'completed',
        };
    }
}
