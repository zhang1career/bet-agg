<?php

declare(strict_types=1);

namespace App\Http\Controllers\api;

use App\Components\ApiResponse;
use App\Exceptions\bet\BetPlaceRequestIdException;
use App\Exceptions\FoundationAuthRequiredException;
use App\Http\Controllers\Controller;
use App\Models\BetOrder;
use App\Services\mall\BetPlaceService;
use App\Services\mall\FoundationUser;
use App\Services\MallDictionaryService;
use App\Services\user\UserFoundationGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BetPlaceController extends Controller
{
    private const REQUEST_ID_HEADER = 'X-Request-Id';

    public function __construct(
        private readonly UserFoundationGateway $foundationGateway,
        private readonly BetPlaceService $betPlace,
        private readonly MallDictionaryService $dict,
    ) {}

    /** Validates odds, debits stake, accepts order in one DB transaction; same {@code X-Request-Id} retries return the existing order. */
    public function store(Request $request): JsonResponse
    {
        $user = $this->requireAuthenticatedUser($request);
        $uid = FoundationUser::id($user);

        $idemKey = $this->requirePlaceRequestId($request);

        $request->validate([
            'lines' => 'required|array|min:1|max:1',
            'lines.0.kid' => 'required|integer|min:1',
            'lines.0.stake_points' => 'required|integer|min:1',
            'lines.0.expected_odds_millis' => 'required|integer|min:1000',
        ]);

        /** @var list<array{kid: int, stake_points: int, expected_odds_millis: int}> $lines */
        $lines = [];
        foreach ($request->input('lines', []) as $line) {
            if (! is_array($line)) {
                continue;
            }
            $lines[] = [
                'kid' => (int) ($line['kid'] ?? 0),
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
            'order' => $this->serializeOrder($order),
            'is_replay' => $result['is_replay'],
            '_dict' => $this->dict->resolve(['bet_order_status']),
        ];

        // Replays return 200 (no new order created); first acceptance returns 201.
        return response()->json(ApiResponse::ok($payload), $result['is_replay'] ? 200 : 201);
    }

    /**
     * @return array<string, mixed>
     *
     * @throws FoundationAuthRequiredException
     */
    private function requireAuthenticatedUser(Request $request): array
    {
        $token = trim((string) $request->header('X-User-Access-Token', ''));
        if ($token === '') {
            throw new FoundationAuthRequiredException(
                'Authentication required. Send header: X-User-Access-Token: <access_token> (raw JWT, no Bearer prefix).'
            );
        }

        return $this->foundationGateway->fetchCurrentUser($request);
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

    /**
     * @return array<string, mixed>
     */
    private function serializeOrder(BetOrder $order): array
    {
        $order->loadMissing('lines');

        $lines = [];
        foreach ($order->lines as $item) {
            $lines[] = [
                'kid' => $item->kid,
                'stake_points' => $item->stake_points,
                'decimal_odds_millis' => $item->decimal_odds_millis,
                'potential_return_points' => $item->potential_return_points,
                'odds_snapshot' => $item->odds_snapshot,
                'result' => $item->result->value,
            ];
        }

        return [
            'id' => $order->id,
            'uid' => $order->uid,
            'status' => $order->status->value,
            'total_price' => $order->total_price,
            'points_held' => $order->points_held,
            'ct' => $order->ct,
            'ut' => $order->ut,
            'lines' => $lines,
        ];
    }
}
