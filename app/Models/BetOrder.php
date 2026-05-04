<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BetOrderStatus;
use App\Enums\CheckoutPhase;
use App\Models\Concerns\HasMillisTimestamps;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $uid Foundation user id (from `UserFoundationGateway` / outbound profile)
 * @property BetOrderStatus $status
 * @property int $total_price Total stake points (integer); denormalized from lines
 * @property int $points_held Stake removed from user's points wallet after checkout completes (usually equals total_price)
 * @property int $ct
 * @property int $ut
 * @property CheckoutPhase $checkout_phase
 * @property bool $ext_inventory
 * @property string $ext_id
 * @property-read Collection<int, BetOrderLine> $lines
 */
class BetOrder extends Model
{
    use HasMillisTimestamps;

    public $timestamps = false;

    protected $table = 'order';

    protected $fillable = [
        'uid',
        'status',
        'total_price',
        'points_held',
        'ct',
        'ut',
        'checkout_phase',
        'ext_inventory',
        'ext_id',
    ];

    protected $casts = [
        'uid' => 'integer',
        'status' => BetOrderStatus::class,
        'total_price' => 'integer',
        'points_held' => 'integer',
        'ct' => 'integer',
        'ut' => 'integer',
        'checkout_phase' => CheckoutPhase::class,
        'ext_inventory' => 'boolean',
        'ext_id' => 'string',
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
