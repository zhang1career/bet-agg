<?php

declare(strict_types=1);

namespace App\Http\Controllers\api;

use App\Components\ApiResponse;
use App\Exceptions\ConfigurationMissingException;
use App\Exceptions\FoundationAuthRequiredException;
use App\Http\Concerns\RequiresFoundationUser;
use App\Http\Controllers\Controller;
use App\Models\PointsBalance;
use App\Services\mall\FoundationUser;
use App\Services\user\UserFoundationGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PredictionReputationController extends Controller
{
    use RequiresFoundationUser;

    public function __construct(
        private readonly UserFoundationGateway $foundationGateway,
    ) {}

    /**
     * @throws FoundationAuthRequiredException
     * @throws ConfigurationMissingException
     */
    public function show(Request $request): JsonResponse
    {
        $user = $this->requireAuthenticatedUser($request);
        $uid = FoundationUser::id($user);

        $row = PointsBalance::query()->firstOrCreate(
            ['uid' => $uid],
            ['balance' => 0],
        );

        $this->logHandledApiRequest($request, ['handler' => 'prediction.reputation.show', 'uid' => $uid]);

        return response()->json(ApiResponse::ok([
            'score' => (int) $row->balance,
        ]));
    }
}
