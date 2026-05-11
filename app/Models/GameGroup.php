<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\concerns\HasMillisTimestamps;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Business grouping for CMS-backed games (e.g. tournament phase). Pivot {@code x} uses columns {@code pid}, {@code gid}.
 *
 * @property int $id
 * @property string $code Stable external identifier (e.g. fifa-2026-group)
 * @property int $ct
 * @property int $ut
 * @property-read Collection<int, Game> $games
 * @property-read Collection<int, GameSubject> $subjects
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
        return $this->belongsToMany(Game::class, 'x', 'pid', 'gid');
    }

    /**
     * @return BelongsToMany<GameSubject, $this>
     */
    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(GameSubject::class, 'y', 'pid', 'sid');
    }
}
