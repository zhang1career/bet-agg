<?php

declare(strict_types=1);

namespace App\Services\mall\settlement;

use Illuminate\Support\Facades\DB;
use Paganini\Batch\Contracts\TransactionRunnerContract;
use Throwable;

/**
 * {@link TransactionRunnerContract} backed by Laravel {@see DB::transaction}.
 */
final class LaravelDbTransactionRunner implements TransactionRunnerContract
{
    /**
     * @template T
     *
     * @param  callable(): T  $work
     * @return T
     *
     * @throws Throwable
     */
    public function run(callable $work): mixed
    {
        return DB::transaction(static fn () => $work());
    }
}
