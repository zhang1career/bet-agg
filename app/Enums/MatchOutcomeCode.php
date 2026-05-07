<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Synthetic 1X2 leg keys (stored under {@code order_item.selection} as {@code {"code": "..."}} for that type).
 */
enum MatchOutcomeCode: string
{
    case HomeWin = 'home_win';
    case Draw = 'draw';
    case AwayWin = 'away_win';

    /**
     * @return list<string>
     */
    public static function allValues(): array
    {
        return [
            self::HomeWin->value,
            self::Draw->value,
            self::AwayWin->value,
        ];
    }
}
