<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SettleJobStatus;
use App\Models\concerns\HasMillisTimestamps;
use Illuminate\Database\Eloquent\Model;

/**
 * Paganini batch header for settlement runs ({@code biz_key} like {@code settle:game:{id}:{millis}}).
 *
 * @property int $id
 * @property string $biz_key
 * @property array<string, mixed>|null $payload
 * @property int $total
 * @property int $cursor_offset
 * @property int $success_count
 * @property int $failure_count
 * @property SettleJobStatus $status
 * @property string|null $last_error
 * @property int $ct
 * @property int $ut
 */
class SettleJob extends Model
{
    use HasMillisTimestamps;

    public $timestamps = false;

    protected $table = 'settle_job';

    protected $fillable = [
        'biz_key',
        'payload',
        'total',
        'cursor_offset',
        'success_count',
        'failure_count',
        'status',
        'last_error',
        'ct',
        'ut',
    ];

    protected $casts = [
        'payload' => 'array',
        'total' => 'integer',
        'cursor_offset' => 'integer',
        'success_count' => 'integer',
        'failure_count' => 'integer',
        'status' => SettleJobStatus::class,
        'ct' => 'integer',
        'ut' => 'integer',
    ];
}
