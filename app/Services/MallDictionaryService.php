<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\HasDictionaryLabel;
use App\Enums\BetLineResult;
use App\Enums\BetOrderStatus;
use App\Enums\GameStatus;
use App\Enums\MarketStatus;
use App\Enums\SettleJobStatus;
use BackedEnum;
use InvalidArgumentException;
use UnitEnum;

final class MallDictionaryService
{
    /** @var array<string, class-string<UnitEnum>> */
    private const CODE_TO_ENUM = [
        'bet_order_status' => BetOrderStatus::class,
        'market_status' => MarketStatus::class,
        'game_status' => GameStatus::class,
        'order_item_result' => BetLineResult::class,
        'settle_job_status' => SettleJobStatus::class,
    ];

    /**
     * @param  list<string>  $codes  Requested dictionary codes (unknown codes are ignored).
     * @return array<string, list<array{k: string, v: string}>>
     */
    public function resolve(array $codes): array
    {
        $out = [];
        foreach ($codes as $code) {
            if ($code === '') {
                continue;
            }
            $enumClass = self::CODE_TO_ENUM[$code] ?? null;
            if ($enumClass === null) {
                continue;
            }
            $out[$code] = $this->itemsForEnum($enumClass);
        }

        return $out;
    }

    /**
     * @param  class-string<UnitEnum>  $enumClass
     * @return list<array{k: string, v: string}>
     */
    private function itemsForEnum(string $enumClass): array
    {
        if (! enum_exists($enumClass)) {
            throw new InvalidArgumentException('Invalid enum class: '.$enumClass);
        }

        $cases = $enumClass::cases();
        $rows = [];
        foreach ($cases as $case) {
            if (! $case instanceof BackedEnum) {
                throw new InvalidArgumentException('Dictionary enums must be backed: '.$enumClass);
            }
            $rows[] = [
                'k' => $this->labelForCase($case),
                'v' => (string) $case->value,
            ];
        }

        return $rows;
    }

    private function labelForCase(BackedEnum $case): string
    {
        if ($case instanceof HasDictionaryLabel) {
            return $case->label();
        }

        return $case->name;
    }
}
