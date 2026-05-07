<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MarketType;
use App\Enums\MatchOutcomeCode;
use App\Models\concerns\HasMillisTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $game_id
 * @property MarketType $type
 * @property string $name
 * @property int $status
 * @property array<string, int>|null $odds_millis outcome_code => 欧洲盘×1000，结构随 {@see MarketType} 变化；胜平负见 {@see MatchOutcomeCode}
 * @property int $ct
 * @property int $ut
 */
class Market extends Model
{
    use HasMillisTimestamps;

    public const STATUS_OPEN = 1;

    public const STATUS_SUSPENDED = 2;

    public const STATUS_SETTLED = 3;

    public $timestamps = false;

    protected $table = 'biz_market';

    protected $fillable = [
        'game_id',
        'type',
        'name',
        'status',
        'odds_millis',
        'ct',
        'ut',
    ];

    protected $casts = [
        'game_id' => 'integer',
        'type' => MarketType::class,
        'name' => 'string',
        'status' => 'integer',
        'odds_millis' => 'array',
        'ct' => 'integer',
        'ut' => 'integer',
    ];

    /**
     * @return array<string, int> outcome_code => odds×1000
     */
    public function outcomeOddsMillisMap(): array
    {
        $raw = $this->odds_millis;

        return is_array($raw) ? $raw : [];
    }

    /**
     * 胜平负三路赔率写入 {@code odds_millis}。
     *
     * @return array<string, int>
     */
    public static function oneX2OddsMillisJson(int $homeMillis, int $drawMillis, int $awayMillis): array
    {
        return [
            MatchOutcomeCode::HomeWin->value => $homeMillis,
            MatchOutcomeCode::Draw->value => $drawMillis,
            MatchOutcomeCode::AwayWin->value => $awayMillis,
        ];
    }

    /**
     * @return BelongsTo<Game, $this>
     */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class, 'game_id');
    }
}
