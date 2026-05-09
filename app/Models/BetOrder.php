<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BetOrderStatus;
use App\Models\concerns\HasMillisTimestamps;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Atomic bet order produced by {@code POST /api/bet/place}: created in
 * {@link BetOrderStatus::Accepted} state with stake already debited
 * from the user's points balance and credited to the bookmaker pool. Settlement
 * later transitions to {@code Won} / {@code Lost} / {@code Void}.
 *
 * Client-supplied snowflake {@code idem_key} is unique per {@code uid}; replays
 * of {@code POST /api/bet/place} with the same key return this row.
 *
 * @property int $id
 * @property int $uid Foundation user id (from `UserFoundationGateway`)
 * @property int $idem_key Idempotency key (decimal snowflake; obtain via POST /api/bet/snowflake)
 * @property BetOrderStatus $status
 * @property int $total_price Total stake points (denormalized from lines)
 * @property int $points_held Stake removed from user's wallet (usually equals total_price)
 * @property int $ct
 * @property int $ut
 * @property-read Collection<int, BetOrderLine> $lines
 */
class BetOrder extends Model
{
    use HasMillisTimestamps;

    public $timestamps = false;

    protected $table = 'bet_order';

    protected $fillable = [
        'uid',
        'idem_key',
        'status',
        'total_price',
        'points_held',
        'ct',
        'ut',
    ];

    protected $casts = [
        'uid' => 'integer',
        'idem_key' => 'integer',
        'status' => BetOrderStatus::class,
        'total_price' => 'integer',
        'points_held' => 'integer',
        'ct' => 'integer',
        'ut' => 'integer',
    ];

    /**
     * @return HasMany<BetOrderLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(BetOrderLine::class, 'oid');
    }

    /**
     * @return HasMany<BetOrderLine, $this>
     */
    public function items(): HasMany
    {
        return $this->lines();
    }
}
