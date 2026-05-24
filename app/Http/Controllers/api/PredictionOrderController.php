<?php

declare(strict_types=1);

namespace App\Http\Controllers\api;

use App\Components\ApiResponse;
use App\Exceptions\ConfigurationMissingException;
use App\Exceptions\FoundationAuthRequiredException;
use App\Http\Concerns\RequiresFoundationUser;
use App\Http\Controllers\Controller;
use App\Services\mall\FoundationUser;
use App\Services\mall\OrderApiService;
use App\Services\MallDictionaryService;
use App\Services\user\UserFoundationGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PredictionOrderController extends Controller
{
    use RequiresFoundationUser;

    public function __construct(
        private readonly UserFoundationGateway $foundationGateway,
        private readonly OrderApiService $orders,
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

        $pack = $this->orders->listForUser(FoundationUser::id($user), $perPage);
        $pack['_dict'] = $this->dict->resolve(['bet_order_status']);

        $this->logHandledApiRequest($request, ['handler' => 'prediction.orders.index']);

        return response()->json(ApiResponse::ok($pack));
    }

    /**
     * @throws FoundationAuthRequiredException
     * @throws ConfigurationMissingException
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $this->requireAuthenticatedUser($request);

        $this->logHandledApiRequest($request, ['handler' => 'prediction.orders.show', 'order_id' => $id]);

        return response()->json(ApiResponse::ok([
            'order' => $this->orders->detailForUser(FoundationUser::id($user), $id),
            '_dict' => $this->dict->resolve(['bet_order_status', 'order_item_result']),
        ]));
    }
}
