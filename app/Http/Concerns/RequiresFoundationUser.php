<?php

declare(strict_types=1);

namespace App\Http\Concerns;

use App\Exceptions\ConfigurationMissingException;
use App\Exceptions\FoundationAuthRequiredException;
use App\Services\user\UserFoundationGateway;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Paganini\Aggregation\Exceptions\DownstreamServiceException;
use Psr\SimpleCache\InvalidArgumentException;

/**
 * Shared Foundation user resolution for API controllers.
 *
 * The consuming class must expose {@see UserFoundationGateway} as a constructor-promoted
 * property {@code $foundationGateway} (same pattern as {@see BetPlaceController}).
 */
trait RequiresFoundationUser
{
    /**
     * @return array<string, mixed>
     *
     * @throws BindingResolutionException
     * @throws ConfigurationMissingException
     * @throws ConnectionException
     * @throws DownstreamServiceException
     * @throws FoundationAuthRequiredException
     * @throws InvalidArgumentException
     */
    protected function requireAuthenticatedUser(Request $request): array
    {
        $token = trim((string)$request->header('X-User-Access-Token', ''));
        if ($token === '') {
            throw new FoundationAuthRequiredException(
                'Authentication required. Send header: X-User-Access-Token: <access_token> (raw JWT, no Bearer prefix).'
            );
        }

        return $this->foundationGateway->fetchCurrentUser($request);
    }
}
