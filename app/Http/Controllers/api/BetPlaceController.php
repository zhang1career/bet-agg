<?php

declare(strict_types=1);

namespace App\Http\Controllers\api;

use App\Components\ApiResponse;
use App\Exceptions\bet\BetPlaceRequestIdException;
use App\Exceptions\ConfigurationMissingException;
use App\Exceptions\FoundationAuthRequiredException;
use App\Http\Concerns\RequiresFoundationUser;
use App\Http\Controllers\Controller;
use App\Services\mall\BetPlaceService;
use App\Services\mall\FoundationUser;
use App\Services\MallDictionaryService;
use App\Services\user\UserFoundationGateway;
use App\Support\BetOrderApiArray;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BetPlaceController extends Controller
{
    use RequiresFoundationUser;

    private const REQUEST_ID_HEADER = 'X-Request-Id';

    public function __construct(
        private readonly UserFoundationGateway $foundationGateway,
        private readonly BetPlaceService $betPlace,
        private readonly MallDictionaryService $dict,
    ) {}

    /**
     * @throws FoundationAuthRequiredException
     * @throws ConfigurationMissingException
     */
    public function store(Request $request): JsonResponse
    {
        $user = $this->requireAuthenticatedUser($request);
        $uid = FoundationUser::id($user);

        $idemKey = $this->requirePlaceRequestId($request);

        $request->validate([
            'lines' => 'required|array|min:1|max:1',
            'lines.0.market_id' => 'required|integer|min:1',
            'lines.0.outcome_code' => 'required|string|max:32',
            'lines.0.stake_points' => 'required|integer|min:1',
            'lines.0.expected_odds_millis' => 'required|integer|min:1000',
        ]);

        /** @var list<array{market_id: int, outcome_code: string, stake_points: int, expected_odds_millis: int}> $lines */
        $lines = [];
        foreach ($request->input('lines', []) as $line) {
            if (! is_array($line)) {
                continue;
            }
            $lines[] = [
                'market_id' => (int) ($line['market_id'] ?? 0),
                'outcome_code' => trim((string) ($line['outcome_code'] ?? '')),
                'stake_points' => (int) ($line['stake_points'] ?? 0),
                'expected_odds_millis' => (int) ($line['expected_odds_millis'] ?? 0),
            ];
        }

        $result = $this->betPlace->place($uid, $idemKey, $lines);
        $order = $result['order'];

        $this->logHandledApiRequest($request, [
            'handler' => 'bet.place.store',
            'order_id' => $order->id,
            'idem_key' => $idemKey,
            'is_replay' => $result['is_replay'],
        ]);

        $payload = [
            'order' => BetOrderApiArray::detail($order),
            'is_replay' => $result['is_replay'],
            '_dict' => $this->dict->resolve(['bet_order_status']),
        ];

        return response()->json(ApiResponse::ok($payload), $result['is_replay'] ? 200 : 201);
    }

    private function requirePlaceRequestId(Request $request): int
    {
        $raw = trim((string) $request->header(self::REQUEST_ID_HEADER, ''));
        if ($raw === '') {
            throw new BetPlaceRequestIdException;
        }
        if (! ctype_digit($raw) || (int) $raw < 1) {
            throw new BetPlaceRequestIdException('X-Request-Id must be a positive decimal snowflake id.');
        }

        return (int) $raw;
    }
}
