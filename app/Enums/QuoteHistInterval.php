<?php

declare(strict_types=1);

namespace App\Enums;

use ValueError;

enum QuoteHistInterval: string
{
    case Hour = '1h';
    case Day = '1d';

    public function intervalCode(): int
    {
        return match ($this) {
            self::Hour => 1,
            self::Day => 2,
        };
    }

    public function bucketMillis(): int
    {
        return match ($this) {
            self::Hour => 3_600_000,
            self::Day => 86_400_000,
        };
    }

    public static function fromQuery(string $raw): self
    {
        $trimmed = trim($raw);
        if ($trimmed === '') {
            return self::Hour;
        }

        return match ($trimmed) {
            self::Hour->value => self::Hour,
            self::Day->value => self::Day,
            default => throw new ValueError('Invalid quote history interval: '.$trimmed),
        };
    }
}
