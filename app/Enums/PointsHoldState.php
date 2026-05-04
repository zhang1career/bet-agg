<?php

declare(strict_types=1);

namespace App\Enums;

use App\Contracts\HasDictionaryLabel;

enum PointsHoldState: int implements HasDictionaryLabel
{
    case TryPending = 10;
    case TrySucceeded = 20;
    case Confirmed = 40;
    case RolledBack = 50;

    /** Manual console / API ledger entry (not part of TCC try/confirm/cancel). */
    case AdminLedger = 60;

    /** Automated settlement credit (win); append-only ledger. */
    case SettlementPayout = 70;

    /** Automated void refund (stake return). */
    case SettlementRefund = 71;

    /** Accepted bet stake credited to internal bookmaker account. */
    case BookStakeCredit = 72;

    /** Bookmaker liquidity debited when paying an accepted bet win (paired with SettlementPayout on user). */
    case BookPayoutDebit = 73;

    public function label(): string
    {
        return match ($this) {
            self::TryPending => 'try pending',
            self::TrySucceeded => 'try succeeded',
            self::Confirmed => 'confirmed',
            self::RolledBack => 'rolled back',
            self::AdminLedger => 'admin ledger',
            self::SettlementPayout => 'settlement payout',
            self::SettlementRefund => 'settlement refund',
            self::BookStakeCredit => 'book stake credit',
            self::BookPayoutDebit => 'book payout debit',
        };
    }
}
