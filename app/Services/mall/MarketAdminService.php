<?php

declare(strict_types=1);

namespace App\Services\mall;

use App\Enums\MarketStatus;
use App\Enums\MarketType;
use App\Models\Market;
use App\Repos\mall\GameRepo;
use App\Repos\mall\MarketRepo;
use App\Repos\mall\SettlementConsoleRepo;
use App\Services\mall\serv_fd\CmsGameClient;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

final readonly class MarketAdminService
{
    public function __construct(
        private MarketRepo $markets,
        private GameRepo $games,
        private CmsGameClient $cmsGames,
        private SettlementConsoleRepo $settlementConsole,
    ) {}

    /**
     * @return LengthAwarePaginator<int, Market>
     */
    public function paginateIndex(int $perPage): LengthAwarePaginator
    {
        return $this->markets->paginateForAdmin($perPage);
    }

    /**
     * @return Collection<int, \App\Models\Game>
     */
    public function listGamesForSelect(): Collection
    {
        return $this->games->listForAdminSelect();
    }

    /**
     * @param  Collection<int, Market>  $markets
     * @return array<int, array<string, mixed>>
     */
    public function cmsByRawIdsForMarkets(Collection $markets): array
    {
        $rawIds = $markets
            ->map(static fn (Market $m): int => (int) ($m->game?->raw_id ?? 0))
            ->unique()
            ->filter(static fn (int $r): bool => $r >= 1)
            ->values()
            ->all();
        if ($rawIds === []) {
            return [];
        }

        try {
            return $this->cmsGames->findManyById($rawIds);
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @return array{
     *     market: Market,
     *     settlementOrderCounts: array<int, int>,
     *     settlementLineCounts: array<int, int>,
     *     settlementJobs: \Illuminate\Support\Collection<int, \App\Models\SettleJob>
     * }
     */
    public function showViewData(int $id): array
    {
        $market = $this->findForShow($id);

        return [
            'market' => $market,
            'settlementOrderCounts' => $this->settlementConsole->distinctOrderCountsByStatusForMarket($market->id),
            'settlementLineCounts' => $this->settlementConsole->lineResultCountsForMarket($market->id),
            'settlementJobs' => $this->settlementConsole->recentJobsForGame($market->gid),
        ];
    }

    public function findForShow(int $id): Market
    {
        $market = $this->markets->findForAdminShow($id);
        if ($market === null) {
            throw new NotFoundHttpException();
        }

        return $market;
    }

    public function findForModal(int $id): ?Market
    {
        return $this->markets->findForAdmin($id);
    }

    public function create(int $gameId, MarketType $type, string $name, MarketStatus $status): void
    {
        $this->markets->createForAdmin($gameId, $type, $name, $status);
    }

    public function update(
        int $id,
        int $gameId,
        MarketType $type,
        string $name,
        MarketStatus $status,
    ): void {
        $market = $this->markets->findForAdmin($id);
        if ($market === null) {
            throw new NotFoundHttpException();
        }

        $this->markets->updateForAdmin($market, $gameId, $type, $name, $status);
    }

    public function delete(int $id): void
    {
        $market = $this->markets->findForAdmin($id);
        if ($market === null) {
            throw new NotFoundHttpException();
        }

        $this->markets->delete($market);
    }
}
