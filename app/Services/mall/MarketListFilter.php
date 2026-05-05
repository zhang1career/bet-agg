<?php

declare(strict_types=1);

namespace App\Services\mall;

/**
 * Validated filter inputs for {@see SportMarketCatalogService::listMarkets}.
 */
final readonly class MarketListFilter
{
    /**
     * @param  list<int>  $statuses  Empty = no status filter.
     * @param  int|null  $localGameId  {@code biz_game.id} (NOT raw_id) to restrict to a single game.
     * @param  bool  $onlyMarketsUnderOpenGame  When true, only markets whose parent {@code biz_game.status} is OPEN are returned.
     */
    public function __construct(
        public array $statuses,
        public ?int $localGameId,
        public ?int $updatedAfterMillis,
        public bool $onlyMarketsUnderOpenGame,
        public bool $includeSelections,
    ) {}
}
