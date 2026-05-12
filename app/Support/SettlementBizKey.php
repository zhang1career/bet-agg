<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Stable prefix / parsing for batch settlement job keys ({@code settle_job.biz_key}).
 */
final class SettlementBizKey
{
    public static function prefixForGame(int $gameId): string
    {
        return 'settle:game:'.$gameId;
    }

    /**
     * Inverse of {@see prefixForGame} for persisted batch rows ({@code biz_key} may include a millis suffix).
     */
    public static function gameIdFromBizKey(string $bizKey): ?int
    {
        if (preg_match('/^settle:game:(\d+):/', $bizKey, $m) !== 1) {
            return null;
        }

        $id = (int) $m[1];

        return $id >= 1 ? $id : null;
    }
}
