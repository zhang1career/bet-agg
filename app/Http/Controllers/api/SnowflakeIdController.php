<?php

declare(strict_types=1);

namespace App\Http\Controllers\api;

use App\Components\ApiResponse;
use App\Exceptions\ConfigurationMissingException;
use App\Exceptions\FoundationAuthRequiredException;
use App\Http\Concerns\RequiresFoundationUser;
use App\Http\Controllers\Controller;
use App\Services\mall\FoundationUser;
use App\Services\user\FoundationSnowflakeClient;
use App\Services\user\UserFoundationGateway;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Paganini\Aggregation\Exceptions\DownstreamServiceException;
use Psr\SimpleCache\InvalidArgumentException;

/**
 * Authenticated snowflake mint at {@code POST /api/bet/snowflake}; proxies to service_foundation using {@code SF_SNOWFLAKE_ACCESS_KEY}.
 * Use the returned {@code id} as {@code X-Request-Id} on {@code POST /api/bet/place}.
 */
final class SnowflakeIdController extends Controller
{
    use RequiresFoundationUser;

    public function __construct(
        private readonly UserFoundationGateway $foundationGateway,
        private readonly FoundationSnowflakeClient $snowflake,
    ) {}

    /**
     * @throws BindingResolutionException
     * @throws ConfigurationMissingException
     * @throws ConnectionException
     * @throws DownstreamServiceException
     * @throws FoundationAuthRequiredException
     * @throws InvalidArgumentException
     */
    public function __invoke(Request $request): JsonResponse
    {
        $user = $this->requireAuthenticatedUser($request);

        $id = $this->snowflake->mintNextId();

        $this->logHandledApiRequest($request, [
            'handler' => 'prediction.snowflake.mint',
            'uid' => FoundationUser::id($user),
        ]);

        return response()->json(ApiResponse::ok([
            'id' => $id,
        ]));
    }
}
