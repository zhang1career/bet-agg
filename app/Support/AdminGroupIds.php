<?php

declare(strict_types=1);

namespace App\Support;

final class AdminGroupIds
{
    /**
     * @param  array<string, mixed>  $validated
     * @return list<int>
     */
    public static function fromValidated(array $validated): array
    {
        /** @var list<int|string> $raw */
        $raw = $validated['group_ids'] ?? [];

        return array_values(array_unique(array_map(static fn ($x): int => (int) $x, $raw)));
    }
}
