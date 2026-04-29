<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BetLineResult;
use App\Models\Concerns\HasMillisTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $oid bet_order.id
 * @property int $selection_id
 * @property int $stake_points
 * @property array<string, mixed>|null $odds_snapshot
 * @property int $decimal_odds_millis
 * @property int $potential_return_points
 * @property BetLineResult|null $line_result
 * @property int $ct
 * @property int $ut
 */
class BetOrderLine extends Model
{
    use HasMillisTimestamps;

    public $timestamps = false;

    protected $table = 'bet_order_line';

    protected $fillable = [
        'oid',
        'selection_id',
        'stake_points',
        'odds_snapshot',
        'decimal_odds_millis',
        'potential_return_points',
        'line_result',
        'ct',
        'ut',
    ];

    protected $casts = [
        'oid' => 'integer',
        'selection_id' => 'integer',
        'stake_points' => 'integer',
        'odds_snapshot' => 'array',
        'decimal_odds_millis' => 'integer',
        'potential_return_points' => 'integer',
        'line_result' => BetLineResult::class,
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
        return $this->belongsTo(SportSelection::class, 'selection_id');
    }
}
