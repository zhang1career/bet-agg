<?php

declare(strict_types=1);

namespace App\Exceptions\bet;

use Paganini\Constants\ResponseConstant;

/**
 * Selection / market / parent game is not in an open state at place-time.
 * 409 Conflict because the resource exists but is not in a state that accepts
 * the action.
 */
final class SelectionNotAcceptingException extends BetDomainException
{
    public function __construct(
        public readonly int $kid,
        string $reason,
    ) {
        parent::__construct(sprintf('Selection %d is not accepting bets: %s', $kid, $reason));
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
