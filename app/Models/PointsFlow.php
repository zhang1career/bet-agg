<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PointsFlowKind;
use App\Models\concerns\HasMillisTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Row in {@code points_flow} (ledger; {@code oid} → {@code bet_order.id}).
 *
 * @property int $id
 * @property int $uid
 * @property int $oid
 * @property int $amount
 * @property PointsFlowKind $state
 * @property int $ct
 * @property int $ut
 */
class PointsFlow extends Model
{
    use HasMillisTimestamps;

    public $timestamps = false;

    protected $table = 'points_flow';

    protected $fillable = [
        'uid',
        'oid',
        'amount',
        'state',
        'ct',
        'ut',
    ];

    protected $casts = [
        'uid' => 'integer',
        'oid' => 'integer',
        'amount' => 'integer',
        'state' => PointsFlowKind::class,
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
}
