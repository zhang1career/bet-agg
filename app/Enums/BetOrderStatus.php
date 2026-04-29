<?php

declare(strict_types=1);

namespace App\Enums;

use App\Contracts\HasDictionaryLabel;
use ValueError;

enum BetOrderStatus: int implements HasDictionaryLabel
{
    case Pending = 0;
    /** Stake confirmed (TCC confirm); bet is live. */
    case Accepted = 1;
    case Cancelled = 2;
    case Won = 3;
    case Lost = 4;
    case Void = 5;

    /**
     * @return list<int>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'pending',
            self::Accepted => 'accepted',
            self::Cancelled => 'cancelled',
            self::Won => 'won',
            self::Lost => 'lost',
            self::Void => 'void',
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::Pending => $next === self::Accepted || $next === self::Cancelled,
            self::Accepted => $next === self::Won || $next === self::Lost || $next === self::Void,
            self::Cancelled, self::Won, self::Lost, self::Void => false,
        };
    }

    public static function fromClient(string|int $value): self
    {
        if (is_int($value)) {
            return self::from($value);
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            throw new ValueError('Empty BetOrderStatus.');
        }

        if (ctype_digit($trimmed)) {
            return self::from((int) $trimmed);
        }

        $normalized = strtolower($trimmed);
        if ($normalized === 'init') {
            return self::Pending;
        }
        if ($normalized === 'cancel') {
            return self::Cancelled;
        }

        return match ($normalized) {
            'pending' => self::Pending,
            'paid', 'accepted' => self::Accepted,
            'cancelled' => self::Cancelled,
            'won' => self::Won,
            'lost' => self::Lost,
            'void' => self::Void,
            default => throw new ValueError('Invalid BetOrderStatus: '.$trimmed),
        };
    }
}
