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
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(50, max(1, (int) $request->query('per_page', 15)));

        $pack = $this->catalog->listOpenSelections($page, $perPage);
        $this->logHandledApiRequest($request, ['handler' => 'bet.selections.index']);

        return response()->json(ApiResponse::ok($pack));
    }

    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $row = $this->catalog->getSelectionDetail($id);
        } catch (\RuntimeException $e) {
            return response()->json(ApiResponse::error(40401, $e->getMessage()), 404);
        }

        $this->logHandledApiRequest($request, ['handler' => 'bet.selections.show', 'selection_id' => $id]);

        return response()->json(ApiResponse::ok($row));
    }
}
