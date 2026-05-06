<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\concerns\HasMillisTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Business grouping for CMS-backed games (e.g. tournament phase). Pivot {@code biz_x} uses columns {@code group_id}, {@code gid}.
 *
 * @property int $id
 * @property string $code Stable external identifier (e.g. fifa-2026-group)
 * @property int $ct
 * @property int $ut
 */
class GameGroup extends Model
{
    use HasMillisTimestamps;

    public $timestamps = false;

    protected $table = 'biz_game_group';

    protected $fillable = [
        'code',
        'ct',
        'ut',
    ];

    protected $casts = [
        'id' => 'integer',
        'code' => 'string',
        'ct' => 'integer',
        'ut' => 'integer',
    ];

    /**
     * @return BelongsToMany<Game, $this>
     */
    public function games(): BelongsToMany
    {
        return $this->belongsToMany(Game::class, 'biz_x', 'group_id', 'gid');
    }
}
