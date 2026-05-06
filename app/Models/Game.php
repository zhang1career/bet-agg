<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\concerns\HasMillisTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Local betting aggregate for a CMS game: {@code raw_id} is the external game id;
 * title, media, and kickoff time are owned by CMS (not stored here).
 *
 * @property int $id Local surrogate primary key
 * @property int $raw_id External/CMS game identifier (unique)
 * @property int $status 1 open, 2 closed, 3 settled
 * @property list<int>|null $winning_selection_ids JSON when settled
 * @property int $ct
 * @property int $ut
 */
class Game extends Model
{
    use HasMillisTimestamps;

    public const STATUS_OPEN = 1;

    public const STATUS_CLOSED = 2;

    public const STATUS_SETTLED = 3;

    public $timestamps = false;

    protected $table = 'biz_game';

    protected $fillable = [
        'raw_id',
        'status',
        'winning_selection_ids',
        'ct',
        'ut',
    ];

    protected $casts = [
        'id' => 'integer',
        'raw_id' => 'integer',
        'status' => 'integer',
        'winning_selection_ids' => 'array',
        'ct' => 'integer',
        'ut' => 'integer',
    ];

    /**
     * @return HasMany<Market, $this>
     */
    public function markets(): HasMany
    {
        return $this->hasMany(Market::class, 'game_id');
    }

    /**
     * @return BelongsToMany<GameGroup, $this>
     */
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(GameGroup::class, 'biz_x', 'gid', 'group_id');
    }
}
