<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PointsHoldState;
use App\Models\Concerns\HasMillisTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Points ledger rows: hold lifecycle (try / confirm / cancel), manual admin entries, settlement postings.
 *
 * @property int $id
 * @property int $uid
 * @property int $oid 0 = not linked to an order row
 * @property int $amount_minor
 * @property PointsHoldState $state
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
        'amount_minor',
        'state',
        'ct',
        'ut',
    ];

    protected $casts = [
        'uid' => 'integer',
        'oid' => 'integer',
        'amount_minor' => 'integer',
        'state' => PointsHoldState::class,
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
