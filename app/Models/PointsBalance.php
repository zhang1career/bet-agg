<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasMillisTimestamps;
use Illuminate\Database\Eloquent\Model;

/**
 * User points wallet (single account unit for betting and payouts).
 *
 * @property int $id
 * @property int $uid
 * @property int $balance
 * @property int $ct
 * @property int $ut
 */
class PointsBalance extends Model
{
    use HasMillisTimestamps;

    public $timestamps = false;

    protected $table = 'points_balance';

    protected $fillable = [
        'uid',
        'balance',
        'ct',
        'ut',
    ];

    protected $casts = [
        'id' => 'integer',
        'uid' => 'integer',
        'balance' => 'integer',
        'ct' => 'integer',
        'ut' => 'integer',
    ];
}
