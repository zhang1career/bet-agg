<?php

declare(strict_types=1);

namespace App\Http\Controllers\api;

use App\Components\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\mall\SportMarketCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BetMarketController extends Controller
{
    public function __construct(
        private readonly SportMarketCatalogService $catalog,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->query(), [
            'event_id' => 'sometimes|integer|min:1',
        ]);
        if ($validator->fails()) {
            return response()->json(ApiResponse::error(100, $validator->errors()->first()), 422);
        }

        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(50, max(1, (int) $request->query('per_page', 15)));

        $eventId = $request->query('event_id');
        $eventIdFilter = $eventId === null || $eventId === '' ? null : (int) $eventId;

        $pack = $this->catalog->listOpenMarkets($page, $perPage, $eventIdFilter);
        $this->logHandledApiRequest($request, ['handler' => 'bet.markets.index']);

        return response()->json(ApiResponse::ok($pack));
    }

    public function show(Request $request, int $market_id): JsonResponse
    {
        try {
            $row = $this->catalog->getMarketDetail($market_id);
        } catch (\RuntimeException $e) {
            return response()->json(ApiResponse::error(40401, $e->getMessage()), 404);
        }

        $this->logHandledApiRequest($request, ['handler' => 'bet.markets.show', 'market_id' => $market_id]);

        return response()->json(ApiResponse::ok($row));
    }
}
