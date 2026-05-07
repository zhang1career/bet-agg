<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BetLineResult;
use App\Models\concerns\HasMillisTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $oid
 * @property int $market_id
 * @property array<string, mixed>|null $selection Chosen option JSON; 1X2 uses key {@code code} (e.g. home_win)
 * @property int $stake_points
 * @property array<string, mixed>|null $odds_snapshot
 * @property int $decimal_odds_millis
 * @property int $potential_return_points
 * @property BetLineResult $result
 * @property int $ct
 * @property int $ut
 */
class BetOrderLine extends Model
{
    use HasMillisTimestamps;

    public $timestamps = false;

    protected $table = 'order_item';

    protected $fillable = [
        'oid',
        'market_id',
        'selection',
        'stake_points',
        'odds_snapshot',
        'decimal_odds_millis',
        'potential_return_points',
        'result',
        'ct',
        'ut',
    ];

    protected $casts = [
        'oid' => 'integer',
        'market_id' => 'integer',
        'selection' => 'array',
        'stake_points' => 'integer',
        'odds_snapshot' => 'array',
        'decimal_odds_millis' => 'integer',
        'potential_return_points' => 'integer',
        'result' => BetLineResult::class,
        'ct' => 'integer',
        'ut' => 'integer',
    ];

    /**
     * Value compared to settlement payload {@code winners} / {@code voids} for supported types (1X2 leg codes).
     */
    public function selectionSettlementKey(): string
    {
        $sel = $this->selection;
        if (! is_array($sel)) {
            return '';
        }

        return trim((string) ($sel['code'] ?? ''));
    }

    /**
     * @return BelongsTo<BetOrder, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(BetOrder::class, 'oid');
    }

    /**
     * @return BelongsTo<Market, $this>
     */
    public function market(): BelongsTo
    {
        return $this->belongsTo(Market::class, 'market_id');
    }
}
