<?php

declare(strict_types=1);

namespace App\Http\Controllers\api;

use App\Components\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\mall\LeaderboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public leaderboard: uid + score (from points balance).
 */
class PredictionLeaderboardController extends Controller
{
    public function __construct(
        private readonly LeaderboardService $leaderboard,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(100, max(1, (int) $request->query('per_page', 25)));

        $pack = $this->leaderboard->list($page, $perPage);

        $this->logHandledApiRequest($request, ['handler' => 'prediction.leaderboard.index']);

        return response()->json(ApiResponse::ok($pack));
    }
}
