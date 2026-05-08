<?php

declare(strict_types=1);

namespace App\Services\mall;

use App\Enums\MarketType;
use App\Enums\MatchOutcomeCode;
use App\Models\Game;
use App\Models\Market;
use RuntimeException;

/**
 * 胜平负等业务：盘口选项不存表，由赛事双方主体 + {@see Market} 赔率现场合成。
 */
final class SyntheticMatchMarket
{
    /**
     * @return list<array{outcome_code: string, label: string, current_odds_millis: int}>
     */
    public function legsForApi(Market $market, ?Game $game): array
    {
        if ($market->type !== MarketType::OneX2) {
            return [];
        }
        $map = $market->outcomeOddsMillisMap();
        $a = $game?->sideASubject !== null ? (string) $game->sideASubject->name : '主队';
        $b = $game?->sideBSubject !== null ? (string) $game->sideBSubject->name : '客队';

        return [
            [
                'outcome_code' => MatchOutcomeCode::HomeWin->value,
                'label' => $a.'胜',
                'current_odds_millis' => ($map[MatchOutcomeCode::HomeWin->value] ?? 0),
            ],
            [
                'outcome_code' => MatchOutcomeCode::Draw->value,
                'label' => '平局',
                'current_odds_millis' => ($map[MatchOutcomeCode::Draw->value] ?? 0),
            ],
            [
                'outcome_code' => MatchOutcomeCode::AwayWin->value,
                'label' => $b.'胜',
                'current_odds_millis' => ($map[MatchOutcomeCode::AwayWin->value] ?? 0),
            ],
        ];
    }

    public function oddsMillisForOutcome(Market $market, string $outcomeCode): int
    {
        if ($market->type !== MarketType::OneX2) {
            throw new RuntimeException('Market type does not support synthetic legs.');
        }
        $code = MatchOutcomeCode::tryFrom($outcomeCode);
        if ($code === null) {
            throw new RuntimeException('Invalid outcome_code.');
        }
        $map = $market->outcomeOddsMillisMap();

        return ($map[$code->value] ?? 0);
    }
}
