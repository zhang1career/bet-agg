<?php

declare(strict_types=1);

namespace App\Exceptions\bet;

use Paganini\Constants\ResponseConstant;

final class IdempotencyKeyMissingException extends BetDomainException
{
    public function __construct(string $message = 'Idempotency-Key header is required (snowflake integer from POST /api/snowflake/id).')
    {
        parent::__construct($message);
    }

    public function httpStatus(): int
    {
        return 400;
    }

    public function errorCode(): int
    {
        return ResponseConstant::RET_MISSING_PARAM;
    }
}
