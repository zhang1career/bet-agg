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
 * title, media, kickoff in CMS. Sides reference {@see GameSubject} as **A** and **B** ({@code side_a_subj_id} / {@code side_b_subj_id}), without implying home/away.
 *
 * @property int $id
 * @property int $raw_id
 * @property int|null $side_a_subj_id
 * @property int|null $side_b_subj_id
 * @property int $status
 * @property array{winners?: list<string>, voids?: list<string>}|null $settle_outcomes JSON: {@code winners} = payout legs, {@code voids} = refund legs.
 * @property int $ct
 * @property int $ut
 */
class Game extends Model
{
    use HasMillisTimestamps;

    public const STATUS_OPEN = 1;

    public const STATUS_CLOSED = 2;

    public const STATUS_SETTLED = 3;

    /** Result recorded; scheduler should run {@see BetSettlementService::applyGameResult}. */
    public const STATUS_PENDING_SETTLEMENT = 4;

    public $timestamps = false;

    protected $table = 'biz_game';

    protected $fillable = [
        'raw_id',
        'side_a_subj_id',
        'side_b_subj_id',
        'status',
        'settle_outcomes',
        'ct',
        'ut',
    ];

    protected $casts = [
        'id' => 'integer',
        'raw_id' => 'integer',
        'side_a_subj_id' => 'integer',
        'side_b_subj_id' => 'integer',
        'status' => 'integer',
        'settle_outcomes' => 'array',
        'ct' => 'integer',
        'ut' => 'integer',
    ];

    /**
     * @return BelongsTo<GameSubject, $this>
     */
    public function sideASubject(): BelongsTo
    {
        return $this->belongsTo(GameSubject::class, 'side_a_subj_id');
    }

    /**
     * @return BelongsTo<GameSubject, $this>
     */
    public function sideBSubject(): BelongsTo
    {
        return $this->belongsTo(GameSubject::class, 'side_b_subj_id');
    }

    /**
     * @return HasMany<Market, $this>
     */
    public function markets(): HasMany
    {
        return $this->hasMany(Market::class, 'gid');
    }

    /**
     * @return BelongsToMany<GameGroup, $this>
     */
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(GameGroup::class, 'x', 'gid', 'pid');
    }
}
