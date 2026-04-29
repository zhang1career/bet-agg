<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasMillisTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property string $name
 * @property int $starts_at Unix ms
 * @property int $status 1 open, 2 closed, 3 settled
 * @property int $ct
 * @property int $ut
 */
class SportEvent extends Model
{
    use HasMillisTimestamps;

    public const STATUS_OPEN = 1;

    public const STATUS_CLOSED = 2;

    public const STATUS_SETTLED = 3;

    public $timestamps = false;

    protected $table = 'sport_event';

    protected $fillable = ['name', 'starts_at', 'status', 'ct', 'ut'];

    protected $casts = [
        'starts_at' => 'integer',
        'status' => 'integer',
        'ct' => 'integer',
        'ut' => 'integer',
    ];

    /**
     * @return HasMany<SportMarket, $this>
     */
    public function markets(): HasMany
    {
        return $this->hasMany(SportMarket::class, 'event_id');
    }

    /**
     * @return HasOne<SportEventResult, $this>
     */
    public function result(): HasOne
    {
        return $this->hasOne(SportEventResult::class, 'event_id');
    }
}
