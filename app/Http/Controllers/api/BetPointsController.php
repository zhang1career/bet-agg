<?php

declare(strict_types=1);

namespace App\Http\Controllers\api;

use App\Components\ApiResponse;
use App\Exceptions\ConfigurationMissingException;
use App\Exceptions\FoundationAuthRequiredException;
use App\Http\Concerns\RequiresFoundationUser;
use App\Http\Controllers\Controller;
use App\Services\mall\FoundationUser;
use App\Services\mall\PointsService;
use App\Services\user\UserFoundationGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BetPointsController extends Controller
{
    use RequiresFoundationUser;

    public function __construct(
        private readonly UserFoundationGateway $foundationGateway,
        private readonly PointsService $points,
    ) {}

    /**
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
}
