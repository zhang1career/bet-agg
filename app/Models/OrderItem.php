<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BetLineResult;
use App\Models\concerns\HasMillisTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Row in {@code order_item} (one leg per {@see BetOrder}).
 *
 * @property int $id
 * @property int $oid {@code order_item.oid} → {@see BetOrder::$id}
 * @property int $mid {@code order_item.mid} → {@see Market::$id}
 * @property array<string, mixed>|null $selection
 * @property string $pick_label
 * @property BetLineResult $result
 * @property int $ct
 * @property int $ut
 */
class OrderItem extends Model
{
    use HasMillisTimestamps;

    public $timestamps = false;

    protected $table = 'order_item';

    protected $fillable = [
        'oid',
        'mid',
        'selection',
        'pick_label',
        'result',
        'ct',
        'ut',
    ];

    protected $casts = [
        'oid' => 'integer',
        'mid' => 'integer',
        'selection' => 'array',
        'pick_label' => 'string',
        'result' => BetLineResult::class,
        'ct' => 'integer',
        'ut' => 'integer',
    ];

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
        return $this->belongsTo(Market::class, 'mid');
    }
}
