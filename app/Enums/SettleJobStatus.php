<?php

declare(strict_types=1);

namespace App\Enums;

use App\Contracts\HasDictionaryLabel;

/**
 * Matches {@code settle_job.status} (Paganini batch job).
 */
enum SettleJobStatus: int implements HasDictionaryLabel
{
    case Pending = 0;
    case Running = 1;
    case Completed = 2;
    case Partial = 3;
    case Failed = 4;

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'pending',
            self::Running => 'running',
            self::Completed => 'completed',
            self::Partial => 'partial',
            self::Failed => 'failed',
        };
    }
}
