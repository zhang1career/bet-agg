<?php

declare(strict_types=1);

namespace App\Http\Controllers\api;

use App\Components\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\mall\SportMarketCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BetGameController extends Controller
{
    public function __construct(
        private readonly SportMarketCatalogService $catalog,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(50, max(1, (int) $request->query('per_page', 15)));

        $pack = $this->catalog->listOpenGames($page, $perPage);
        $this->logHandledApiRequest($request, ['handler' => 'bet.games.index']);

        return response()->json(ApiResponse::ok($pack));
    }

    public function show(Request $request, int $game_id): JsonResponse
    {
        $row = $this->catalog->getGameDetail($game_id);
        $this->logHandledApiRequest($request, ['handler' => 'bet.games.show', 'game_id' => $game_id]);

        return response()->json(ApiResponse::ok($row));
    }
}
