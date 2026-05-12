<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\concerns\HasMillisTimestamps;
use Illuminate\Database\Eloquent\Model;

/**
 * Row in {@code points_balance} (user score in {@code balance}; not redeemable currency).
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
        'uid' => 'integer',
        'balance' => 'integer',
        'ct' => 'integer',
        'ut' => 'integer',
    ];
}
