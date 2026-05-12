<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Single JSON column on {@code biz_game}: {@code { "winners": [...], "voids": [...] }}.
 */
final class SettleOutcomes
{
    public const KEY_WINNERS = 'winners';

    public const KEY_VOIDS = 'voids';

    /**
     * @param  list<string>  $winners
     * @param  list<string>  $voids
     * @return array{winners: list<string>, voids: list<string>}
     */
    public static function pack(array $winners, array $voids): array
    {
        return [
            self::KEY_WINNERS => array_values($winners),
            self::KEY_VOIDS => array_values($voids),
        ];
    }

    /**
     * @return array{0: list<string>, 1: list<string>}
     */
    public static function unpack(?array $settleOutcomes): array
    {
        if (! is_array($settleOutcomes)) {
            return [[], []];
        }

        return [
            self::normalizeStringList($settleOutcomes[self::KEY_WINNERS] ?? []),
            self::normalizeStringList($settleOutcomes[self::KEY_VOIDS] ?? []),
        ];
    }

    /**
     * API/catalog: always include both keys.
     *
     * @return array{winners: list<string>, voids: list<string>}
     */
    public static function forApi(?array $settleOutcomes): array
    {
        [$w, $v] = self::unpack($settleOutcomes);

        return self::pack($w, $v);
    }

    /**
     * @return list<string>
     */
    private static function normalizeStringList(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $v) {
            if (! is_string($v)) {
                continue;
            }
            $t = trim($v);
            if ($t !== '') {
                $out[$t] = true;
            }
        }

        return array_keys($out);
    }
}
