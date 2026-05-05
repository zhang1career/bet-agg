<?php

declare(strict_types=1);

namespace App\Exceptions\bet;

use Paganini\Constants\ResponseConstant;

/**
 * Odds for a selection differ from the {@code expected_odds_millis} the agent
 * supplied. Agent should re-read /api/bet/markets and re-place if still desired.
 */
final class OddsMovedException extends BetDomainException
{
    public function __construct(
        public readonly int $kid,
        public readonly int $expectedMillis,
        public readonly int $actualMillis,
    ) {
        parent::__construct(
            sprintf(
                'Odds moved for selection %d (expected=%d, actual=%d).',
                $kid,
                $expectedMillis,
                $actualMillis,
            )
        );
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
