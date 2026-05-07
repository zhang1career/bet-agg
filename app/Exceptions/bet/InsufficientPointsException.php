<?php

declare(strict_types=1);

namespace App\Exceptions\bet;

use Paganini\Constants\ResponseConstant;

/**
 * User's points balance is below the requested stake. 422 because the request
 * shape is valid but precondition (balance) is not met.
 */
final class InsufficientPointsException extends BetDomainException
{
    public function __construct(
        public readonly int $required,
        public readonly int $available,
    ) {
        parent::__construct(
            sprintf('Insufficient points: required=%d, available=%d.', $required, $available)
        );
    }

    public function httpStatus(): int
    {
        return 422;
    }

    public function errorCode(): int
    {
        return ResponseConstant::RET_BUSINESS_ERROR;
    }
}
