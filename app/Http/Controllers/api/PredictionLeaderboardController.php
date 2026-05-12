<?php

declare(strict_types=1);

namespace App\Http\Controllers\api;

use App\Components\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\PointsBalance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public leaderboard: uid + score (from points balance).
 */
class PredictionLeaderboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(100, max(1, (int) $request->query('per_page', 25)));

        $paginator = PointsBalance::query()
            ->orderByDesc('balance')
            ->orderBy('uid')
            ->paginate($perPage, ['*'], 'page', $page);

        $base = ($paginator->currentPage() - 1) * $paginator->perPage();
        $items = [];
        $i = 0;
        foreach ($paginator->items() as $row) {
            $i++;
            $items[] = [
                'rank' => $base + $i,
                'uid' => (int) $row->uid,
                'score' => (int) $row->balance,
            ];
        }

        $this->logHandledApiRequest($request, ['handler' => 'prediction.leaderboard.index']);

        return response()->json(ApiResponse::ok([
            'items' => $items,
            'pagination' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
            ],
        ]));
    }
}
