<?php

declare(strict_types=1);

namespace App\Http\Controllers\api;

use App\Components\ApiResponse;
use App\Enums\GameStatus;
use App\Http\Controllers\Controller;
use App\Services\mall\CatalogService;
use App\Services\mall\GameListFilter;
use App\Services\MallDictionaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PredictionGameController extends Controller
{
    /**
     * @var array<string, array{0: string, 1: string}>
     */
    private const SORT_MAP = [
        'id' => ['id', 'asc'],
        '-id' => ['id', 'desc'],
    ];

    public function __construct(
        private readonly CatalogService $catalog,
        private readonly MallDictionaryService $dict,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status' => 'sometimes|string|max:64',
            'updated_after' => 'sometimes|integer|min:0',
            'sort' => 'sometimes|string|in:id,-id',
            'group_code' => 'sometimes|string|max:192',
        ]);

        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(50, max(1, (int) $request->query('per_page', 15)));

        $filter = new GameListFilter(
            statuses: $this->parseStatusList($request->query('status')),
            updatedAfterMillis: $this->intOrNull($request->query('updated_after')),
            sort: $this->mapSort($request->query('sort')),
            groupCode: $this->trimmedNonEmptyStringOrNull($request->query('group_code')),
        );

        $pack = $this->catalog->listGames($filter, $page, $perPage);
        $pack['_dict'] = $this->dict->resolve(['game_status']);

        $this->logHandledApiRequest($request, ['handler' => 'prediction.games.index']);

        return response()->json(ApiResponse::ok($pack));
    }

    public function show(Request $request, int $game_id): JsonResponse
    {
        $row = $this->catalog->getGameDetail($game_id);
        $row['_dict'] = $this->dict->resolve(['game_status']);

        $this->logHandledApiRequest($request, ['handler' => 'prediction.games.show', 'game_id' => $game_id]);

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
        $valid = array_column(GameStatus::cases(), 'value');
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

    /**
     * @return array{0: string, 1: string}|null
     */
    private function mapSort(mixed $raw): ?array
    {
        if (! is_string($raw) || $raw === '') {
            return null;
        }

        return self::SORT_MAP[$raw] ?? null;
    }

    private function trimmedNonEmptyStringOrNull(mixed $raw): ?string
    {
        if (! is_string($raw)) {
            return null;
        }
        $trimmed = trim($raw);

        return $trimmed === '' ? null : $trimmed;
    }
}
