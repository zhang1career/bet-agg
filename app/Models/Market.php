<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MarketType;
use App\Models\concerns\HasMillisTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $gid Local {@see Game} id ({@code biz_market.gid}).
 * @property MarketType $type
 * @property string $name
 * @property int $status
 * @property int $ct
 * @property int $ut
 */
class Market extends Model
{
    use HasMillisTimestamps;

    public const STATUS_OPEN = 1;

    public const STATUS_SUSPENDED = 2;

    public const STATUS_SETTLED = 3;

    public $timestamps = false;

    protected $table = 'biz_market';

    protected $fillable = [
        'gid',
        'type',
        'name',
        'status',
        'ct',
        'ut',
    ];

    protected $casts = [
        'gid' => 'integer',
        'type' => MarketType::class,
        'name' => 'string',
        'status' => 'integer',
        'ct' => 'integer',
        'ut' => 'integer',
    ];

    /**
     * @return BelongsTo<Game, $this>
     */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class, 'gid');
    }
}
