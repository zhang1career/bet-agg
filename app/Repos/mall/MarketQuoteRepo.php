<?php

declare(strict_types=1);

namespace App\Repos\mall;

use App\Enums\MatchOutcomeCode;
use App\Enums\QuoteHistInterval;
use App\Models\MarketQuote;
use App\Models\MarketQuoteHist;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class MarketQuoteRepo
{
    /**
     * @return Collection<int, MarketQuote>
     */
    public function lockSnapshotsForMarket(int $marketId): Collection
    {
        return MarketQuote::query()
            ->where('mid', $marketId)
            ->lockForUpdate()
            ->get()
            ->keyBy(static fn (MarketQuote $row): string => $row->outcome_code);
    }

    public function seedEmptySnapshots(int $marketId, int $utMillis): void
    {
        foreach (MatchOutcomeCode::allValues() as $outcomeCode) {
            MarketQuote::query()->insert([
                'mid' => $marketId,
                'outcome_code' => $outcomeCode,
                'pick_count' => 0,
                'share_bp' => 0,
                'ut' => $utMillis,
            ]);
        }
    }

    /**
     * @param  array<string, array{pick_count: int, share_bp: int}>  $byOutcome
     */
    public function saveSnapshots(int $marketId, array $byOutcome, int $utMillis): void
    {
        foreach (MatchOutcomeCode::allValues() as $outcomeCode) {
            $row = $byOutcome[$outcomeCode] ?? ['pick_count' => 0, 'share_bp' => 0];
            MarketQuote::query()
                ->where('mid', $marketId)
                ->where('outcome_code', $outcomeCode)
                ->update([
                    'pick_count' => $row['pick_count'],
                    'share_bp' => $row['share_bp'],
                    'ut' => $utMillis,
                ]);
        }
    }

    /**
     * @param  list<int>  $marketIds
     * @return array<int, list<MarketQuote>>
     */
    public function findSnapshotsByMarketIds(array $marketIds): array
    {
        if ($marketIds === []) {
            return [];
        }

        $rows = MarketQuote::query()
            ->whereIn('mid', $marketIds)
            ->orderBy('mid')
            ->orderBy('outcome_code')
            ->get();

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(int) $row->mid][] = $row;
        }

        return $grouped;
    }

    /**
     * @param  array<string, array{pick_count: int, share_bp: int}>  $byOutcome
     */
    public function upsertHistBucket(
        int $marketId,
        QuoteHistInterval $interval,
        int $bucketStart,
        array $byOutcome,
        int $nowMillis,
    ): void {
        foreach (MatchOutcomeCode::allValues() as $outcomeCode) {
            $row = $byOutcome[$outcomeCode] ?? ['pick_count' => 0, 'share_bp' => 0];
            DB::table('biz_market_quote_hist')->upsert(
                [[
                    'mid' => $marketId,
                    'bucket_start' => $bucketStart,
                    'interval_code' => $interval->intervalCode(),
                    'outcome_code' => $outcomeCode,
                    'pick_count' => $row['pick_count'],
                    'share_bp' => $row['share_bp'],
                    'ct' => $nowMillis,
                ]],
                ['mid', 'interval_code', 'bucket_start', 'outcome_code'],
                ['pick_count', 'share_bp'],
            );
        }
    }

    /**
     * @return list<MarketQuoteHist>
     */
    public function findHistBuckets(
        int $marketId,
        QuoteHistInterval $interval,
        int $fromMillis,
        int $toMillis,
        ?string $outcomeCode,
    ): array {
        $query = MarketQuoteHist::query()
            ->where('mid', $marketId)
            ->where('interval_code', $interval->intervalCode())
            ->whereBetween('bucket_start', [$fromMillis, $toMillis])
            ->orderBy('bucket_start')
            ->orderBy('outcome_code');

        if ($outcomeCode !== null) {
            $query->where('outcome_code', $outcomeCode);
        }

        return $query->get()->all();
    }
}
