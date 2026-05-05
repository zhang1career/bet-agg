<?php

declare(strict_types=1);

namespace App\Http\Controllers\api;

use App\Components\ApiResponse;
use App\Enums\SportMarketStatus;
use App\Http\Controllers\Controller;
use App\Services\mall\MarketListFilter;
use App\Services\mall\SportMarketCatalogService;
use App\Services\MallDictionaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BetMarketController extends Controller
{
    public function __construct(
        private readonly SportMarketCatalogService $catalog,
        private readonly MallDictionaryService $dict,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'game_id' => 'sometimes|integer|min:1',
            'status' => 'sometimes|string|max:64',
            'updated_after' => 'sometimes|integer|min:0',
            'include_selections' => 'sometimes|in:0,1,true,false',
        ]);

        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(50, max(1, (int) $request->query('per_page', 15)));

        $filter = new MarketListFilter(
            statuses: $this->parseStatusList($request->query('status')),
            localGameId: $this->intOrNull($request->query('game_id')),
            updatedAfterMillis: $this->intOrNull($request->query('updated_after')),
            // Default behaviour: hide markets whose parent game is closed/settled — agents wouldn't bet on those anyway.
            // Override by passing an explicit `status` filter (e.g. include settled markets for history).
            onlyMarketsUnderOpenGame: ! $request->has('status') && ! $request->has('game_id'),
            includeSelections: $this->parseBool($request->query('include_selections'), default: true),
        );

        $pack = $this->catalog->listMarkets($filter, $page, $perPage);
        $pack['_dict'] = $this->dict->resolve(
            $filter->includeSelections
                ? ['sport_market_status', 'sport_game_status']
                : ['sport_market_status', 'sport_game_status']
        );

        $this->logHandledApiRequest($request, ['handler' => 'bet.markets.index']);

        return response()->json(ApiResponse::ok($pack));
    }

    public function show(Request $request, int $market_id): JsonResponse
    {
        $row = $this->catalog->getMarketDetail($market_id);
        $row['_dict'] = $this->dict->resolve(['sport_market_status', 'sport_game_status']);

        $this->logHandledApiRequest($request, ['handler' => 'bet.markets.show', 'market_id' => $market_id]);

        return response()->json(ApiResponse::ok($row));
    }

    /**
     * @return list<int>
     */
    private function parseStatusList(mixed $raw): array
    {
        if (! is_string($raw) || trim($raw) === '') {
            return [];
        }

        $parts = array_filter(array_map('trim', explode(',', $raw)), static fn (string $s): bool => $s !== '');
        $valid = array_column(SportMarketStatus::cases(), 'value');
        $out = [];
        foreach ($parts as $token) {
            if (! ctype_digit($token)) {
                continue;
            }
            $value = (int) $token;
            if (in_array($value, $valid, true)) {
                $out[] = $value;
            }
        }

        return array_values(array_unique($out));
    }

    private function intOrNull(mixed $raw): ?int
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        if (is_int($raw)) {
            return $raw;
        }
        if (! is_string($raw) || ! ctype_digit($raw)) {
            return null;
        }

        return (int) $raw;
    }

    private function parseBool(mixed $raw, bool $default): bool
    {
        if ($raw === null || $raw === '') {
            return $default;
        }
        if (is_bool($raw)) {
            return $raw;
        }
        if ($raw === '1' || $raw === 'true' || $raw === 1) {
            return true;
        }
        if ($raw === '0' || $raw === 'false' || $raw === 0) {
            return false;
        }

        return $default;
    }
}
