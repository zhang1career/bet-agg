<?php

namespace App\Queue\failed;

use DateTimeInterface;
use Illuminate\Queue\Failed\DatabaseUuidFailedJobProvider;
use Illuminate\Support\Facades\Date;
use Throwable;

class DatabaseUuidFailedJobProviderMillis extends DatabaseUuidFailedJobProvider
{
    /**
     * Log a failed job into storage.
     *
     * @param  string  $connection
     * @param  string  $queue
     * @param  string  $payload
     * @param  Throwable  $exception
     * @return string|null
     */
    public function log($connection, $queue, $payload, $exception): ?string
    {
        $this->getTable()->insert([
            'uuid' => $uuid = json_decode($payload, true)['uuid'],
            'connection' => $connection,
            'queue' => $queue,
            'payload' => $payload,
            'exception' => (string) mb_convert_encoding($exception, 'UTF-8'),
            'failed_at' => (int) floor(microtime(true) * 1000),
        ]);

        return $uuid;
    }

    /**
     * Flush all of the failed jobs from storage.
     *
     * @param  int|null  $hours
     * @return void
     */
    public function flush($hours = null): void
    {
        $this->getTable()->when($hours, function ($query, $hours) {
            $beforeMs = (int) (Date::now()->subHours($hours)->getTimestamp() * 1000);
            $query->where('failed_at', '<=', $beforeMs);
        })->delete();
    }

    /**
     * Prune all of the entries older than the given date.
     *
     * @param DateTimeInterface $before
     * @return int
     */
    public function prune(DateTimeInterface $before): int
    {
        $beforeMs = (int) ($before->getTimestamp() * 1000);
        $query = $this->getTable()->where('failed_at', '<', $beforeMs);

        $totalDeleted = 0;

        do {
            $deleted = $query->take(1000)->delete();
            $totalDeleted += $deleted;
        } while ($deleted !== 0);

        return $totalDeleted;
    }
}
