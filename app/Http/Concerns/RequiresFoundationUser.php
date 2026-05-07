<?php

declare(strict_types=1);

namespace App\Http\Concerns;

use App\Exceptions\ConfigurationMissingException;
use App\Exceptions\FoundationAuthRequiredException;
use Illuminate\Http\Request;

trait RequiresFoundationUser
{
    /**
     * @return array<string, mixed>
     *
     * @throws FoundationAuthRequiredException
     * @throws ConfigurationMissingException
     */
    protected function requireAuthenticatedUser(Request $request): array
    {
        $token = trim((string) $request->header('X-User-Access-Token', ''));
        if ($token === '') {
            throw new FoundationAuthRequiredException(
                'Authentication required. Send header: X-User-Access-Token: <access_token> (raw JWT, no Bearer prefix).'
            );
        }

        return $this->foundationGateway->fetchCurrentUser($request);
    }
}
