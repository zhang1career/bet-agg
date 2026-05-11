<?php

declare(strict_types=1);

namespace App\Exceptions\bet;

use App\Exceptions\ApiJsonExceptionHandler;
use RuntimeException;

/**
 * Base class for prediction API business failures. Each subclass binds
 * a stable {@code RET_*} code (response envelope) and an HTTP status; the
 * application-wide {@see ApiJsonExceptionHandler} renders both
 * uniformly so agents can branch on `errorCode` without parsing free text.
 */
abstract class BetDomainException extends RuntimeException
{
    abstract public function httpStatus(): int;

    abstract public function errorCode(): int;
}
