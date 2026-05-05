<?php

declare(strict_types=1);

namespace App\Services\mall;

use App\Http\Controllers\api\BetGameController;

/**
 * Validated filter inputs for {@see SportMarketCatalogService::listGames}.
 *
 * The HTTP layer is responsible for parsing & validating raw query strings
 * into this shape (see {@see BetGameController}).
 */
final readonly class GameListFilter
{
    /**
     * @param  list<int>  $statuses  Empty = no status filter.
     * @param  array{0: string, 1: string}|null  $sort  [column, direction]; null = default newest-first.
     */
    public function __construct(
        public array $statuses,
        public ?int $updatedAfterMillis,
        public ?array $sort,
    ) {}
}
