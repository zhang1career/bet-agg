<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\MatchOutcomeCode;
use App\Models\Game;
use RuntimeException;

/**
 * 管理台/队列 payload：{@code draw}、{@code void_all}、{@code subject:<id>}
 * → {@see BetSettlementService::applyGameResult} 使用的 outcome 字符串列表。
 */
final class SettlementPayloadResolver
{
    private const PREFIX_SUBJECT = 'subject:';

    /**
     * @return array{winners: list<string>, voids: list<string>}
     */
    public function winnerAndVoidOutcomeCodes(Game $game, string $payload): array
    {
        $payload = trim($payload);
        if ($payload === '') {
            throw new RuntimeException('Empty settlement payload.');
        }
        if ($game->side_a_subj_id === null || $game->side_b_subj_id === null) {
            throw new RuntimeException('Game is missing side A/B subjects.');
        }

        if ($payload === 'draw') {
            return [
                'winners' => [MatchOutcomeCode::Draw->value],
                'voids' => [],
            ];
        }
        if ($payload === 'void_all') {
            return [
                'winners' => [],
                'voids' => MatchOutcomeCode::allValues(),
            ];
        }
        if (! str_starts_with($payload, self::PREFIX_SUBJECT)) {
            throw new RuntimeException('Invalid settlement payload.');
        }
        $sid = (int) substr($payload, strlen(self::PREFIX_SUBJECT));
        if ($sid < 1) {
            throw new RuntimeException('Invalid subject id in payload.');
        }
        $a = (int) $game->side_a_subj_id;
        $b = (int) $game->side_b_subj_id;
        if ($sid === $a) {
            return ['winners' => [MatchOutcomeCode::HomeWin->value], 'voids' => []];
        }
        if ($sid === $b) {
            return ['winners' => [MatchOutcomeCode::AwayWin->value], 'voids' => []];
        }

        throw new RuntimeException('Subject id is not a participant of this game.');
    }
}
