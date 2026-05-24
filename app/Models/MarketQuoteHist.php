<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\concerns\HasMillisTimestamps;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $mid
 * @property int $bucket_start
 * @property int $interval_code
 * @property string $outcome_code
 * @property int $pick_count
 * @property int $share_bp
 * @property int $ct
 */
class MarketQuoteHist extends Model
{
    use HasMillisTimestamps;

    public $timestamps = false;

    protected $table = 'biz_market_quote_hist';

    protected $fillable = [
        'mid',
        'bucket_start',
        'interval_code',
        'outcome_code',
        'pick_count',
        'share_bp',
        'ct',
    ];

    protected $casts = [
        'mid' => 'integer',
        'bucket_start' => 'integer',
        'interval_code' => 'integer',
        'outcome_code' => 'string',
        'pick_count' => 'integer',
        'share_bp' => 'integer',
        'ct' => 'integer',
    ];
}
