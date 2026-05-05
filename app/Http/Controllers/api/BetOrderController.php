<?php

declare(strict_types=1);

namespace App\Http\Controllers\api;

use App\Components\ApiResponse;
use App\Exceptions\FoundationAuthRequiredException;
use App\Http\Controllers\Controller;
use App\Models\BetOrder;
use App\Services\mall\FoundationUser;
use App\Services\MallDictionaryService;
use App\Services\user\UserFoundationGateway;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only order browsing for agents. Bet placement is exclusively done via
 * {@see BetPlaceController}; there is no draft / cancel flow here anymore.
 */
class BetOrderController extends Controller
{
    public function __construct(
        private readonly UserFoundationGateway $foundationGateway,
        private readonly MallDictionaryService $dict,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $this->requireAuthenticatedUser($request);
        $perPage = min(50, max(1, (int) $request->query('per_page', 15)));

        $paginator = BetOrder::query()
            ->where('uid', FoundationUser::id($user))
            ->orderByDesc('id')
            ->paginate($perPage);

        $items = [];
        foreach ($paginator->items() as $order) {
            $items[] = $this->serializeOrderSummary($order);
        }

        $this->logHandledApiRequest($request, ['handler' => 'bet.orders.index']);

        return response()->json(ApiResponse::ok([
            'items' => $items,
            'pagination' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
            ],
            '_dict' => $this->dict->resolve(['bet_order_status']),
        ]));
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $user = $this->requireAuthenticatedUser($request);

        $order = BetOrder::query()
            ->where('id', $id)
            ->where('uid', FoundationUser::id($user))
            ->with('lines')
            ->first();
        if ($order === null) {
            throw (new ModelNotFoundException)->setModel(BetOrder::class, [$id]);
        }

        $this->logHandledApiRequest($request, ['handler' => 'bet.orders.show', 'order_id' => $id]);

        return response()->json(ApiResponse::ok([
            'order' => $this->serializeOrder($order),
            '_dict' => $this->dict->resolve(['bet_order_status']),
        ]));
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

    /**
     * @return array<string, mixed>
     */
    private function serializeOrderSummary(BetOrder $order): array
    {
        return [
            'id' => $order->id,
            'uid' => $order->uid,
            'status' => $order->status->value,
            'total_price' => $order->total_price,
            'ct' => $order->ct,
            'ut' => $order->ut,
        ];
    }
}
