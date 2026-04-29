<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasMillisTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $event_id
 * @property string $market_type
 * @property int $status 1 open, 2 suspended, 3 settled
 * @property int $ct
 * @property int $ut
 */
class SportMarket extends Model
{
    use HasMillisTimestamps;

    public const STATUS_OPEN = 1;

    public const STATUS_SUSPENDED = 2;

    public const STATUS_SETTLED = 3;

    public $timestamps = false;

    protected $table = 'sport_market';

    protected $fillable = ['event_id', 'market_type', 'status', 'ct', 'ut'];

    protected $casts = [
        'event_id' => 'integer',
        'status' => 'integer',
        'ct' => 'integer',
        'ut' => 'integer',
    ];

    /**
     * @return BelongsTo<SportEvent, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(SportEvent::class, 'event_id');
    }

    /**
     * @return HasMany<SportSelection, $this>
     */
    public function selections(): HasMany
    {
        return $this->hasMany(SportSelection::class, 'market_id');
    }
}
