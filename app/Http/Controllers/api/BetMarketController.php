<?php

declare(strict_types=1);

namespace App\Http\Controllers\api;

use App\Components\ApiResponse;
use App\Enums\MarketStatus;
use App\Http\Controllers\Controller;
use App\Services\mall\MarketListFilter;
use App\Services\mall\CatalogService;
use App\Services\MallDictionaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class BetMarketController extends Controller
{
    public function __construct(
        private readonly CatalogService $catalog,
        private readonly MallDictionaryService $dict,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'game_id' => 'sometimes|integer|min:1',
            'status' => 'sometimes|string|max:64',
            'updated_after' => 'sometimes|integer|min:0',
        ]);

        if ($request->filled('status')) {
            $this->assertValidMarketStatusFilter((string) $request->query('status'));
        }

        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(50, max(1, (int) $request->query('per_page', 15)));

        $filter = new MarketListFilter(
            statuses: $this->parseStatusList($request->query('status')),
            localGameId: $this->intOrNull($request->query('game_id')),
            updatedAfterMillis: $this->intOrNull($request->query('updated_after')),
            // Default behaviour: hide markets whose parent game is closed/settled — agents wouldn't bet on those anyway.
            // Override by passing an explicit `status` filter (e.g. include settled markets for history).
            onlyMarketsUnderOpenGame: ! $request->has('status') && ! $request->has('game_id'),
        );

        $pack = $this->catalog->listMarkets($filter, $page, $perPage);
        $pack['_dict'] = $this->dict->resolve(['market_status', 'game_status']);

        $this->logHandledApiRequest($request, ['handler' => 'bet.markets.index']);

        return response()->json(ApiResponse::ok($pack));
    }

    public function show(Request $request, int $market_id): JsonResponse
    {
        $row = $this->catalog->getMarketDetail($market_id);
        $row['_dict'] = $this->dict->resolve(['market_status', 'game_status']);

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
        $valid = array_column(MarketStatus::cases(), 'value');
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

    private function assertValidMarketStatusFilter(string $raw): void
    {
        $trimmed = trim($raw);
        if ($trimmed === '') {
            return;
        }

        $parts = array_filter(array_map('trim', explode(',', $trimmed)), static fn (string $s): bool => $s !== '');
        $valid = array_column(MarketStatus::cases(), 'value');
        foreach ($parts as $token) {
            if (! ctype_digit($token)) {
                throw ValidationException::withMessages([
                    'status' => ['Each status must be a comma-separated market status id (1=open, 2=suspended, 3=settled).'],
                ]);
            }
            if (! in_array((int) $token, $valid, true)) {
                throw ValidationException::withMessages([
                    'status' => ['Unknown market status in filter.'],
                ]);
            }
        }
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
}
