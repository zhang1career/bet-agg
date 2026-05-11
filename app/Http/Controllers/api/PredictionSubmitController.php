<?php

declare(strict_types=1);

namespace App\Http\Controllers\api;

use App\Components\ApiResponse;
use App\Exceptions\bet\PredictionRequestIdException;
use App\Exceptions\ConfigurationMissingException;
use App\Exceptions\FoundationAuthRequiredException;
use App\Http\Concerns\RequiresFoundationUser;
use App\Http\Controllers\Controller;
use App\Services\mall\FoundationUser;
use App\Services\mall\PredictionSubmitService;
use App\Services\MallDictionaryService;
use App\Services\user\UserFoundationGateway;
use App\Support\BetOrderApiArray;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PredictionSubmitController extends Controller
{
    use RequiresFoundationUser;

    private const REQUEST_ID_HEADER = 'X-Request-Id';

    public function __construct(
        private readonly UserFoundationGateway $foundationGateway,
        private readonly PredictionSubmitService $submit,
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

        $idemKey = $this->requireSubmitRequestId($request);

        $request->validate([
            'lines' => 'required|array|min:1|max:1',
            'lines.0.market_id' => 'required|integer|min:1',
            'lines.0.outcome_code' => 'required|string|max:32',
        ]);

        /** @var list<array{market_id: int, outcome_code: string}> $lines */
        $lines = [];
        foreach ($request->input('lines', []) as $line) {
            if (! is_array($line)) {
                continue;
            }
            $lines[] = [
                'market_id' => (int) ($line['market_id'] ?? 0),
                'outcome_code' => trim((string) ($line['outcome_code'] ?? '')),
            ];
        }

        $result = $this->submit->submit($uid, $idemKey, $lines);
        $order = $result['order'];

        $this->logHandledApiRequest($request, [
            'handler' => 'prediction.submit.store',
            'bet_order_id' => $order->id,
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

    private function requireSubmitRequestId(Request $request): int
    {
        $raw = trim((string) $request->header(self::REQUEST_ID_HEADER, ''));
        if ($raw === '') {
            throw new PredictionRequestIdException;
        }
        if (! ctype_digit($raw) || (int) $raw < 1) {
            throw new PredictionRequestIdException('X-Request-Id must be a positive decimal snowflake id.');
        }

        return (int) $raw;
    }
}
