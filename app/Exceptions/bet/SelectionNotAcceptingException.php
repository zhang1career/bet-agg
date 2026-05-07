<?php

declare(strict_types=1);

namespace App\Exceptions\bet;

use Paganini\Constants\ResponseConstant;

/**
 * Market / outcome leg or parent game is not in an open state at place-time.
 */
final class SelectionNotAcceptingException extends BetDomainException
{
    public function __construct(
        public readonly int $marketId,
        public readonly string $outcomeCode,
        string $reason,
    ) {
        parent::__construct(sprintf(
            'Market %d outcome %s is not accepting bets: %s',
            $marketId,
            $outcomeCode,
            $reason,
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
