<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\concerns\HasMillisTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Local betting aggregate for a CMS game: {@code raw_id} is the external game id;
 * title, media, kickoff in CMS. Sides reference {@see GameSubject} (order: A=主场侧, B=客场侧).
 *
 * @property int $id
 * @property int $raw_id
 * @property int|null $side_a_subject_id
 * @property int|null $side_b_subject_id
 * @property int $status
 * @property list<string>|null $winning_outcomes 1X2 synthetic keys ({@code home_win} / {@code draw} / {@code away_win}); persisted as JSON in {@code biz_game.winning_outcomes} (TEXT)
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
        'side_a_subject_id',
        'side_b_subject_id',
        'status',
        'winning_outcomes',
        'ct',
        'ut',
    ];

    protected $casts = [
        'id' => 'integer',
        'raw_id' => 'integer',
        'side_a_subject_id' => 'integer',
        'side_b_subject_id' => 'integer',
        'status' => 'integer',
        'winning_outcomes' => 'array',
        'ct' => 'integer',
        'ut' => 'integer',
    ];

    /**
     * @return BelongsTo<GameSubject, $this>
     */
    public function sideASubject(): BelongsTo
    {
        return $this->belongsTo(GameSubject::class, 'side_a_subject_id');
    }

    /**
     * @return BelongsTo<GameSubject, $this>
     */
    public function sideBSubject(): BelongsTo
    {
        return $this->belongsTo(GameSubject::class, 'side_b_subject_id');
    }

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
