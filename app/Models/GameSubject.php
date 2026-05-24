<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\concerns\HasMillisTimestamps;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * 赛事主体（球队、选手等）。
 *
 * @property int $id
 * @property string $name
 * @property string $icon OSS object key (e.g. {@code subj_icon/{uuid}.png})
 * @property int $ct
 * @property int $ut
 * @property-read Collection<int, GameGroup> $groups
 */
class GameSubject extends Model
{
    use HasMillisTimestamps;

    public $timestamps = false;

    protected $table = 'biz_game_subject';

    protected $fillable = ['name', 'icon', 'ct', 'ut'];

    protected $casts = [
        'id' => 'integer',
        'name' => 'string',
        'icon' => 'string',
        'ct' => 'integer',
        'ut' => 'integer',
    ];

    /**
     * @return BelongsToMany<GameGroup, $this>
     */
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(GameGroup::class, 'y', 'sid', 'pid');
    }
}
