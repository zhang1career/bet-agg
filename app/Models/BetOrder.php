<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BetOrderStatus;
use App\Models\concerns\HasMillisTimestamps;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Row in {@code bet_order} (idem-keyed submission; no stake).
 *
 * @property int $id
 * @property int $uid
 * @property int $idem_key
 * @property BetOrderStatus $status
 * @property int $ct
 * @property int $ut
 * @property-read Collection<int, OrderItem> $lines
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
        'ct',
        'ut',
    ];

    protected $casts = [
        'uid' => 'integer',
        'idem_key' => 'integer',
        'status' => BetOrderStatus::class,
        'ct' => 'integer',
        'ut' => 'integer',
    ];

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'oid');
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->lines();
    }
}
