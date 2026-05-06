<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\concerns\HasMillisTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $market_id
 * @property string $label
 * @property int $current_odds_millis Decimal European odds * 1000
 * @property int $status 1 open, 2 suspended, 3 settled
 * @property int $ct
 * @property int $ut
 */
class Selection extends Model
{
    use HasMillisTimestamps;

    public const STATUS_OPEN = 1;

    public const STATUS_SUSPENDED = 2;

    public const STATUS_SETTLED = 3;

    public $timestamps = false;

    protected $table = 'biz_selection';

    protected $fillable = ['market_id', 'label', 'current_odds_millis', 'status', 'ct', 'ut'];

    protected $casts = [
        'market_id' => 'integer',
        'current_odds_millis' => 'integer',
        'status' => 'integer',
        'ct' => 'integer',
        'ut' => 'integer',
    ];

    /**
     * @return BelongsTo<Market, $this>
     */
    public function market(): BelongsTo
    {
        return $this->belongsTo(Market::class, 'market_id');
    }
}
