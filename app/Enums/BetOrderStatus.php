<?php

declare(strict_types=1);

namespace App\Enums;

use App\Contracts\HasDictionaryLabel;
use ValueError;

enum BetOrderStatus: int implements HasDictionaryLabel
{
    case Pending = 0;
    /** Stake confirmed after checkout / payment; bet is live. */
    case Accepted = 1;
    case Cancelled = 2;
    case Won = 3;
    case Lost = 4;
    case Void = 5;
    /**
     * Settlement attempted but the inner-phase transaction was rolled back
     * (e.g. bookmaker liquidity insufficient at payout time). The order is
     * parked here for manual review; once the underlying issue is resolved
     * the operator can re-run settlement which will transition to a terminal
     * outcome.
     */
    case SettlementFailed = 6;

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
            self::SettlementFailed => 'settlement failed',
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::Pending => $next === self::Accepted || $next === self::Cancelled,
            // Accepted may also park into SettlementFailed when an inner-phase tx aborts.
            self::Accepted => $next === self::Won || $next === self::Lost || $next === self::Void
                || $next === self::SettlementFailed,
            // SettlementFailed is recoverable: operator retries settlement → terminal outcome.
            self::SettlementFailed => $next === self::Won || $next === self::Lost || $next === self::Void,
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
