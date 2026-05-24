<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $mid
 * @property string $outcome_code
 * @property int $pick_count
 * @property int $share_bp
 * @property int $ut
 */
class MarketQuote extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $table = 'biz_market_quote';

    protected $primaryKey = 'mid';

    protected $fillable = [
        'mid',
        'outcome_code',
        'pick_count',
        'share_bp',
        'ut',
    ];

    protected $casts = [
        'mid' => 'integer',
        'outcome_code' => 'string',
        'pick_count' => 'integer',
        'share_bp' => 'integer',
        'ut' => 'integer',
    ];
}
