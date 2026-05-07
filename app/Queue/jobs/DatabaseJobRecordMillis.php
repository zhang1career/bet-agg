<?php

namespace App\Queue\jobs;

use Illuminate\Queue\Jobs\DatabaseJobRecord;

class DatabaseJobRecordMillis extends DatabaseJobRecord
{
    /**
     * Get the current system time as Unix timestamp in milliseconds.
     *
     * @return int
     */
    protected function currentTime(): int
    {
        return (int) floor(microtime(true) * 1000);
    }
}
