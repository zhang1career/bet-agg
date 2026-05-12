<?php

declare(strict_types=1);

namespace App\Services\mall;

use App\Enums\MarketType;
use App\Enums\MatchOutcomeCode;
use App\Models\Game;
use App\Models\Market;
use RuntimeException;

/**
 * Synthetic 1X2 outcome rows for catalog and validation (no pricing).
 */
final class SyntheticMatchMarket
{
    /**
     * @return list<array{outcome_code: string, label: string}>
     */
    public function legsForApi(Market $market, ?Game $game): array
    {
        if ($market->type !== MarketType::OneX2) {
            return [];
        }
        $a = $game?->sideASubject !== null ? (string) $game->sideASubject->name : '主队';
        $b = $game?->sideBSubject !== null ? (string) $game->sideBSubject->name : '客队';

        return [
            [
                'outcome_code' => MatchOutcomeCode::HomeWin->value,
                'label' => $a.'胜',
            ],
            [
                'outcome_code' => MatchOutcomeCode::Draw->value,
                'label' => '平局',
            ],
            [
                'outcome_code' => MatchOutcomeCode::AwayWin->value,
                'label' => $b.'胜',
            ],
        ];
    }

    public function assertValidOutcome(Market $market, string $outcomeCode): void
    {
        if ($market->type !== MarketType::OneX2) {
            throw new RuntimeException('Market type does not support synthetic legs.');
        }
        if (MatchOutcomeCode::tryFrom($outcomeCode) === null) {
            throw new RuntimeException('Invalid outcome_code.');
        }
    }
}
