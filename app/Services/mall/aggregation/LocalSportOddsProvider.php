<?php

declare(strict_types=1);

namespace App\Services\mall\aggregation;

use App\Contracts\UserBusinessServiceContract;
use App\Services\mall\SportSelectionBookService;

/**
 * Read-side ProviderContract: local odds and acceptance for sport selections.
 */
final readonly class LocalSportOddsProvider implements UserBusinessServiceContract
{
    public function __construct(private SportSelectionBookService $book) {}

    public function key(): string
    {
        return 'sport_odds';
    }

    public function supports(array $context): bool
    {
        return isset($context['bet_selection_ids']) && is_array($context['bet_selection_ids']);
    }

    /**
     * @param  array<string, mixed>  $subject
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function fetch(array $subject, array $context): array
    {
        $ids = $context['bet_selection_ids'];
        if (! is_array($ids)) {
            return ['odds_millis' => [], 'accepting' => []];
        }
        $intIds = array_map(static fn (mixed $v): int => (int) $v, $ids);

        return [
            'odds_millis' => $this->book->getOddsMillisBySelectionIds($intIds),
            'accepting' => $this->book->getAcceptingBySelectionIds($intIds),
        ];
    }
}
