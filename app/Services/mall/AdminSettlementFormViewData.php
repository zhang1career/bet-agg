<?php

declare(strict_types=1);

namespace App\Services\mall;

use App\Models\Game;
use App\Support\AdminGameSelectOptionLabels;
use Illuminate\Support\Collection;

/**
 * Builds the payload for the admin settlement form (open games, outcome options, labels).
 */
final readonly class AdminSettlementFormViewData
{
    public function __construct(
        private AdminGameSelectOptionLabels $gameSelectLabels,
    ) {}

    /**
     * @return array{
     *     games: Collection<int, Game>,
     *     outcomesByGame: array<string, list<array{value: string, label: string}>>,
     *     gameSelectLabels: array<int, string>
     * }
     */
    public function build(): array
    {
        $games = Game::query()
            ->where('status', Game::STATUS_OPEN)
            ->whereNotNull('side_a_subject_id')
            ->whereNotNull('side_b_subject_id')
            ->with(['sideASubject', 'sideBSubject'])
            ->orderByDesc('id')
            ->limit(500)
            ->get();

        $outcomesByGame = [];
        foreach ($games as $game) {
            $outcomesByGame[(string) $game->id] = $this->outcomeOptionsForGame($game);
        }

        return [
            'games' => $games,
            'outcomesByGame' => $outcomesByGame,
            'gameSelectLabels' => $this->gameSelectLabels->mapByLocalId($games),
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function outcomeOptionsForGame(Game $game): array
    {
        $aId = (int) $game->side_a_subject_id;
        $bId = (int) $game->side_b_subject_id;
        $aName = $game->sideASubject?->name ?? (string) __('console.settlement.side_a_placeholder');
        $bName = $game->sideBSubject?->name ?? (string) __('console.settlement.side_b_placeholder');

        return [
            [
                'value' => 'subject:'.$aId,
                'label' => (string) __('console.settlement.outcome_a_win', ['name' => $aName]),
            ],
            [
                'value' => 'draw',
                'label' => (string) __('console.settlement.outcome_draw'),
            ],
            [
                'value' => 'subject:'.$bId,
                'label' => (string) __('console.settlement.outcome_b_win', ['name' => $bName]),
            ],
            [
                'value' => 'void_all',
                'label' => (string) __('console.settlement.outcome_void_all'),
            ],
        ];
    }
}
