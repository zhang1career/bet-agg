<?php

declare(strict_types=1);

namespace App\Http\Controllers\api;

use App\Components\ApiResponse;
use App\Exceptions\ConfigurationMissingException;
use App\Exceptions\FoundationAuthRequiredException;
use App\Http\Controllers\Controller;
use App\Services\mall\FoundationUser;
use App\Services\mall\PointsAdminService;
use App\Services\user\UserFoundationGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BetPointsController extends Controller
{
    public function __construct(
        private readonly UserFoundationGateway $foundationGateway,
        private readonly PointsAdminService $points,
    ) {}

    /**
     * Current user's available points balance (integer game points).
     *
     * @throws FoundationAuthRequiredException
     * @throws ConfigurationMissingException
     */
    public function show(Request $request): JsonResponse
    {
        $user = $this->requireAuthenticatedUser($request);

        $balance = $this->points->availableBalance(FoundationUser::id($user));

        $this->logHandledApiRequest($request, ['handler' => 'bet.points.show']);

        return response()->json(ApiResponse::ok([
            'balance' => $balance,
        ]));
    }

    /**
     * @return array<string, mixed>
     *
     * @throws FoundationAuthRequiredException
     * @throws ConfigurationMissingException
     */
    private function requireAuthenticatedUser(Request $request): array
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
