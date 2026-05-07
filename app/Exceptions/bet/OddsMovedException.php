<?php

declare(strict_types=1);

namespace App\Exceptions\bet;

use Paganini\Constants\ResponseConstant;

/**
 * Odds for a synthetic leg differ from {@code expected_odds_millis}.
 */
final class OddsMovedException extends BetDomainException
{
    public function __construct(
        public readonly int $marketId,
        public readonly string $outcomeCode,
        public readonly int $expectedMillis,
        public readonly int $actualMillis,
    ) {
        parent::__construct(sprintf(
            'Odds moved for market %d outcome %s (expected=%d, actual=%d).',
            $marketId,
            $outcomeCode,
            $expectedMillis,
            $actualMillis,
        ));
    }

    public function httpStatus(): int
    {
        return 409;
    }

    public function errorCode(): int
    {
        return ResponseConstant::RET_INVALID_STATE;
    }
}
