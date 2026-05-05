<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BetLineResult;
use App\Models\concerns\HasMillisTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $oid order.id
 * @property int $kid biz_selection.id
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
        'kid',
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
        'kid' => 'integer',
        'stake_points' => 'integer',
        'odds_snapshot' => 'array',
        'decimal_odds_millis' => 'integer',
        'potential_return_points' => 'integer',
        'result' => BetLineResult::class,
        'ct' => 'integer',
        'ut' => 'integer',
    ];

    /**
     * @return BelongsTo<BetOrder, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(BetOrder::class, 'oid');
    }

    /**
     * @return BelongsTo<SportSelection, $this>
     */
    public function selection(): BelongsTo
    {
        return $this->belongsTo(SportSelection::class, 'kid');
    }
}
