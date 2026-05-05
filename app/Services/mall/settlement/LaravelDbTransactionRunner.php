<?php

declare(strict_types=1);

namespace App\Services\mall\settlement;

use Illuminate\Support\Facades\DB;
use Paganini\Batch\Contracts\TransactionRunnerContract;

/**
 * Laravel-aware adapter for {@see TransactionRunnerContract}: delegates to
 * {@code DB::transaction} so the runner integrates with the framework's
 * connection pool, savepoints, and event dispatch instead of bypassing them
 * with a raw PDO handle (the paganini default {@code PdoTransactionRunner} is
 * fine for unit tests but not for app code).
 */
final readonly class LaravelDbTransactionRunner implements TransactionRunnerContract
{
    public function __construct(
        private string $connection = '',
    ) {}

    public function run(callable $work): mixed
    {
        if ($this->connection === '') {
            return DB::transaction($work);
        }

        return DB::connection($this->connection)->transaction($work);
    }
}
