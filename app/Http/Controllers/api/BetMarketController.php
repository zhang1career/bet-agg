<?php

declare(strict_types=1);

namespace App\Http\Controllers\api;

use App\Components\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\mall\SportMarketCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BetMarketController extends Controller
{
    public function __construct(
        private readonly SportMarketCatalogService $catalog,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'game_id' => 'sometimes|integer|min:1',
        ]);

        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(50, max(1, (int) $request->query('per_page', 15)));

        $gameIdRaw = $request->query('game_id');
        $gameIdFilter = $gameIdRaw === null || $gameIdRaw === '' ? null : (int) $gameIdRaw;

        $pack = $this->catalog->listOpenMarkets($page, $perPage, $gameIdFilter);
        $this->logHandledApiRequest($request, ['handler' => 'bet.markets.index']);

        return response()->json(ApiResponse::ok($pack));
    }

    public function show(Request $request, int $market_id): JsonResponse
    {
        $row = $this->catalog->getMarketDetail($market_id);
        $this->logHandledApiRequest($request, ['handler' => 'bet.markets.show', 'market_id' => $market_id]);

        return response()->json(ApiResponse::ok($row));
    }
}
