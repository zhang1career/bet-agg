<?php

declare(strict_types=1);

namespace App\Exceptions\bet;

use Paganini\Constants\ResponseConstant;

final class BetPlaceRequestIdException extends BetDomainException
{
    public function __construct(
        string $message = 'X-Request-Id required (decimal snowflake from POST /api/bet/snowflake on this host).',
    ) {
        parent::__construct($message);
    }

    public function errorCode(): int
    {
        return ResponseConstant::RET_MISSING_PARAM;
    }

    public function httpStatus(): int
    {
        return 400;
    }
}
