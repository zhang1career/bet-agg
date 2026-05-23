<?php

declare(strict_types=1);

namespace App\Http\Controllers\api;

use App\Components\ApiResponse;
use App\Enums\MatchOutcomeCode;
use App\Enums\QuoteHistInterval;
use App\Http\Controllers\Controller;
use App\Services\mall\MarketQuoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use ValueError;

class PredictionMarketQuoteController extends Controller
{
    public function __construct(
        private readonly MarketQuoteService $quotes,
    ) {}

    public function batch(Request $request): JsonResponse
    {
        $request->validate([
            'market_ids' => 'required|string|max:4096',
        ]);

        $marketIds = $this->parseMarketIds((string) $request->query('market_ids'));
        if ($marketIds === []) {
            throw ValidationException::withMessages([
                'market_ids' => ['At least one valid market id is required.'],
            ]);
        }
        if (count($marketIds) > 100) {
            throw ValidationException::withMessages([
                'market_ids' => ['At most 100 market ids per request.'],
            ]);
        }

        $snapshots = $this->quotes->snapshotsForMarkets($marketIds);
        $items = [];
        foreach ($marketIds as $marketId) {
            $items[] = [
                'market_id' => $marketId,
                'quote' => $snapshots[$marketId] ?? MarketQuoteService::emptySnapshot(),
            ];
        }

        $this->logHandledApiRequest($request, [
            'handler' => 'prediction.markets.quotes.batch',
            'market_count' => count($marketIds),
        ]);

        return response()->json(ApiResponse::ok(['items' => $items]));
    }

    public function history(Request $request, int $market_id): JsonResponse
    {
        $request->validate([
            'from' => 'sometimes|integer|min:0',
            'to' => 'sometimes|integer|min:0',
            'interval' => 'sometimes|string|max:8',
            'outcome_code' => 'sometimes|string|max:32',
        ]);

        try {
            $interval = QuoteHistInterval::fromQuery((string) $request->query('interval', QuoteHistInterval::Hour->value));
        } catch (ValueError $e) {
            throw ValidationException::withMessages([
                'interval' => [$e->getMessage()],
            ]);
        }

        $outcomeCode = $request->filled('outcome_code')
            ? trim((string) $request->query('outcome_code'))
            : null;
        if ($outcomeCode !== null && MatchOutcomeCode::tryFrom($outcomeCode) === null) {
            throw ValidationException::withMessages([
                'outcome_code' => ['Unknown outcome_code.'],
            ]);
        }

        $payload = $this->quotes->history(
            $market_id,
            $interval,
            $this->intOrNull($request->query('from')),
            $this->intOrNull($request->query('to')),
            $outcomeCode,
        );

        $this->logHandledApiRequest($request, [
            'handler' => 'prediction.markets.quote.history',
            'market_id' => $market_id,
        ]);

        return response()->json(ApiResponse::ok($payload));
    }

    /**
     * @return list<int>
     */
    private function parseMarketIds(string $raw): array
    {
        $parts = array_filter(array_map('trim', explode(',', $raw)), static fn (string $s): bool => $s !== '');
        $out = [];
        foreach ($parts as $token) {
            if (! ctype_digit($token)) {
                continue;
            }
            $id = (int) $token;
            if ($id >= 1) {
                $out[] = $id;
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
}
