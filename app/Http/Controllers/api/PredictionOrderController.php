<?php

declare(strict_types=1);

namespace App\Http\Controllers\api;

use App\Components\ApiResponse;
use App\Exceptions\ConfigurationMissingException;
use App\Exceptions\FoundationAuthRequiredException;
use App\Http\Concerns\RequiresFoundationUser;
use App\Http\Controllers\Controller;
use App\Models\BetOrder;
use App\Services\mall\FoundationUser;
use App\Services\MallDictionaryService;
use App\Services\user\UserFoundationGateway;
use App\Support\BetOrderApiArray;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PredictionOrderController extends Controller
{
    use RequiresFoundationUser;

    public function __construct(
        private readonly UserFoundationGateway $foundationGateway,
        private readonly MallDictionaryService $dict,
    ) {}

    /**
     * @throws FoundationAuthRequiredException
     * @throws ConfigurationMissingException
     */
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

        $this->logHandledApiRequest($request, ['handler' => 'prediction.orders.index']);

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

    /**
     * @throws FoundationAuthRequiredException
     * @throws ConfigurationMissingException
     */
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

        $this->logHandledApiRequest($request, ['handler' => 'prediction.orders.show', 'order_id' => $id]);

        return response()->json(ApiResponse::ok([
            'order' => BetOrderApiArray::detail($order),
            '_dict' => $this->dict->resolve(['bet_order_status', 'order_item_result']),
        ]));
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
            'ct' => $order->ct,
            'ut' => $order->ut,
        ];
    }
}
