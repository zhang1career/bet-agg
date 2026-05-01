<?php

declare(strict_types=1);

namespace App\Http\Controllers\api;

use App\Components\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\mall\SportMarketCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SportSelectionController extends Controller
{
    public function __construct(
        private readonly SportMarketCatalogService $catalog,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'market_id' => 'sometimes|integer|min:1',
        ]);

        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(50, max(1, (int) $request->query('per_page', 15)));

        $marketIdRaw = $request->query('market_id');
        $marketId = $marketIdRaw === null || $marketIdRaw === '' ? null : (int) $marketIdRaw;

        $pack = $this->catalog->listOpenSelections($page, $perPage, $marketId);
        $this->logHandledApiRequest($request, ['handler' => 'bet.selections.index']);

        return response()->json(ApiResponse::ok($pack));
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $row = $this->catalog->getSelectionDetail($id);
        $this->logHandledApiRequest($request, ['handler' => 'bet.selections.show', 'selection_id' => $id]);

        return response()->json(ApiResponse::ok($row));
    }
}
