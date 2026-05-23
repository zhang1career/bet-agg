<?php

declare(strict_types=1);

namespace App\Services\mall;

use App\Enums\MatchOutcomeCode;
use App\Enums\QuoteHistInterval;
use App\Models\Market;
use App\Models\MarketQuote;
use App\Repos\mall\MarketQuoteRepo;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Crowd prediction distribution (quote) snapshots and history.
 */
final readonly class MarketQuoteService
{
    private const DEFAULT_HISTORY_WINDOW_MS = 7 * 86_400_000;

    public function __construct(
        private MarketQuoteRepo $quotes,
    ) {}

    /**
     * @param  list<int>  $marketIds
     * @return array<int, array<string, mixed>>
     */
    public function snapshotsForMarkets(array $marketIds): array
    {
        $unique = array_values(array_unique(array_filter($marketIds, static fn (int $id): bool => $id >= 1)));
        if ($unique === []) {
            return [];
        }

        $grouped = $this->quotes->findSnapshotsByMarketIds($unique);
        $out = [];
        foreach ($unique as $marketId) {
            $rows = $grouped[$marketId] ?? [];
            $out[$marketId] = $this->serializeSnapshot($rows);
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public function history(
        int $marketId,
        QuoteHistInterval $interval,
        ?int $fromMillis,
        ?int $toMillis,
        ?string $outcomeCode,
    ): array {
        if (Market::query()->whereKey($marketId)->doesntExist()) {
            throw new NotFoundHttpException('Market not found.');
        }

        $to = $toMillis ?? (int) round(microtime(true) * 1000);
        $from = $fromMillis ?? ($to - self::DEFAULT_HISTORY_WINDOW_MS);

        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $rows = $this->quotes->findHistBuckets($marketId, $interval, $from, $to, $outcomeCode);
        $codes = $outcomeCode !== null
            ? [$outcomeCode]
            : MatchOutcomeCode::allValues();

        $pointsByCode = [];
        foreach ($codes as $code) {
            $pointsByCode[$code] = [];
        }

        foreach ($rows as $row) {
            $pointsByCode[$row->outcome_code][] = [
                't' => $row->bucket_start,
                'pick_count' => $row->pick_count,
                'share_bp' => $row->share_bp,
            ];
        }

        $series = [];
        foreach ($codes as $code) {
            $series[] = [
                'outcome_code' => $code,
                'points' => $pointsByCode[$code],
            ];
        }

        return [
            'market_id' => $marketId,
            'interval' => $interval->value,
            'series' => $series,
        ];
    }

    public function recordPick(int $marketId, string $outcomeCode, int $atMillis): void
    {
        if (MatchOutcomeCode::tryFrom($outcomeCode) === null) {
            throw new \RuntimeException('Invalid outcome_code.');
        }

        $locked = $this->quotes->lockSnapshotsForMarket($marketId);
        if ($locked->isEmpty()) {
            $this->quotes->seedEmptySnapshots($marketId, $atMillis);
            $locked = $this->quotes->lockSnapshotsForMarket($marketId);
        }

        $counts = [];
        foreach (MatchOutcomeCode::allValues() as $code) {
            $row = $locked->get($code);
            $counts[$code] = $row !== null ? (int) $row->pick_count : 0;
        }
        $counts[$outcomeCode]++;

        $byOutcome = $this->countsToShares($counts);
        $this->quotes->saveSnapshots($marketId, $byOutcome, $atMillis);

        foreach (QuoteHistInterval::cases() as $interval) {
            $bucketStart = $this->alignBucketStart($atMillis, $interval);
            $this->quotes->upsertHistBucket($marketId, $interval, $bucketStart, $byOutcome, $atMillis);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function emptySnapshot(): array
    {
        $outcomes = [];
        foreach (MatchOutcomeCode::allValues() as $code) {
            $outcomes[] = [
                'outcome_code' => $code,
                'pick_count' => 0,
                'share_bp' => 0,
            ];
        }

        return [
            'as_of' => null,
            'total_picks' => 0,
            'outcomes' => $outcomes,
        ];
    }

    /**
     * @param  list<MarketQuote>  $rows
     * @return array<string, mixed>
     */
    private function serializeSnapshot(array $rows): array
    {
        if ($rows === []) {
            return self::emptySnapshot();
        }

        $byCode = [];
        $asOf = 0;
        $total = 0;
        foreach ($rows as $row) {
            $byCode[$row->outcome_code] = $row;
            $asOf = max($asOf, (int) $row->ut);
            $total += (int) $row->pick_count;
        }

        $outcomes = [];
        foreach (MatchOutcomeCode::allValues() as $code) {
            $row = $byCode[$code] ?? null;
            $outcomes[] = [
                'outcome_code' => $code,
                'pick_count' => $row !== null ? (int) $row->pick_count : 0,
                'share_bp' => $row !== null ? (int) $row->share_bp : 0,
            ];
        }

        return [
            'as_of' => $total > 0 ? $asOf : null,
            'total_picks' => $total,
            'outcomes' => $outcomes,
        ];
    }

    /**
     * @param  array<string, int>  $counts
     * @return array<string, array{pick_count: int, share_bp: int}>
     */
    private function countsToShares(array $counts): array
    {
        $total = array_sum($counts);
        $out = [];
        $remainingBp = 10_000;
        $codes = MatchOutcomeCode::allValues();
        $lastIndex = count($codes) - 1;

        foreach ($codes as $index => $code) {
            $count = $counts[$code] ?? 0;
            if ($total === 0) {
                $shareBp = 0;
            } elseif ($index === $lastIndex) {
                $shareBp = $remainingBp;
            } else {
                $shareBp = intdiv($count * 10_000, $total);
                $remainingBp -= $shareBp;
            }
            $out[$code] = [
                'pick_count' => $count,
                'share_bp' => $shareBp,
            ];
        }

        return $out;
    }

    private function alignBucketStart(int $atMillis, QuoteHistInterval $interval): int
    {
        $bucket = $interval->bucketMillis();

        return intdiv($atMillis, $bucket) * $bucket;
    }
}
