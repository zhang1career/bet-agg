<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Subjects allowed for a game side: union of {@code biz_game_subject} ids linked via
 * {@code biz_y} to any of the given game group ids.
 */
final class GameSubjectScope
{
    /**
     * @param  list<int>  $groupIds
     * @return list<int>
     */
    public static function subjectIdsForGroups(array $groupIds): array
    {
        $groupIds = array_values(array_unique(array_filter(
            array_map(static fn (mixed $v): int => (int) $v, $groupIds),
            static fn (int $id): bool => $id >= 1,
        )));
        if ($groupIds === []) {
            return [];
        }

        /** @var list<int> $out */
        $out = DB::table('biz_y')
            ->whereIn('group_id', $groupIds)
            ->distinct()
            ->orderBy('subject_id')
            ->pluck('subject_id')
            ->all();

        return array_map(static fn (mixed $id): int => (int) $id, $out);
    }
}
