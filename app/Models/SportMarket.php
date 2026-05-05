<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\concerns\HasMillisTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $game_id Local {@see SportGame::$id}
 * @property string $name Display label stored locally (not from CMS).
 * @property int $status
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

    protected $table = 'biz_market';

    protected $fillable = ['game_id', 'name', 'status', 'ct', 'ut'];

    protected $casts = [
        'game_id' => 'integer',
        'name' => 'string',
        'status' => 'integer',
        'ct' => 'integer',
        'ut' => 'integer',
    ];

    /**
     * @return BelongsTo<SportGame, $this>
     */
    public function game(): BelongsTo
    {
        return $this->belongsTo(SportGame::class, 'game_id');
    }

    /**
     * @return HasMany<SportSelection, $this>
     */
    public function selections(): HasMany
    {
        return $this->hasMany(SportSelection::class, 'market_id');
    }
}
