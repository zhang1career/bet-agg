<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasMillisTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property int $starts_at Unix ms
 * @property int $status 1 open, 2 closed, 3 settled
 * @property list<int>|null $winning_selection_ids JSON when settled
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

    protected $table = 'biz_event';

    protected $fillable = ['name', 'starts_at', 'status', 'winning_selection_ids', 'ct', 'ut'];

    protected $casts = [
        'starts_at' => 'integer',
        'status' => 'integer',
        'winning_selection_ids' => 'array',
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
}
