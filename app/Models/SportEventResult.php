<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasMillisTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $event_id
 * @property list<int> $winning_selection_ids
 * @property int $ct
 * @property int $ut
 */
class SportEventResult extends Model
{
    use HasMillisTimestamps;

    public $timestamps = false;

    protected $table = 'sport_event_result';

    protected $fillable = ['event_id', 'winning_selection_ids', 'ct', 'ut'];

    protected $casts = [
        'event_id' => 'integer',
        'winning_selection_ids' => 'array',
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
}
