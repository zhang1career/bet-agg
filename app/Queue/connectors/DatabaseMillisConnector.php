<?php

namespace App\Queue\connectors;

use App\Queue\DatabaseQueueMillis;
use Illuminate\Contracts\Queue\Queue;
use Illuminate\Queue\Connectors\DatabaseConnector;

class DatabaseMillisConnector extends DatabaseConnector
{
    /**
     * Establish a queue connection.
     *
     * @param array $config
     * @return Queue|DatabaseQueueMillis
     */
    public function connect(array $config): Queue|DatabaseQueueMillis
    {
        return new DatabaseQueueMillis(
            $this->connections->connection($config['connection'] ?? null),
            $config['table'],
            $config['queue'],
            $config['retry_after'] ?? 60,
            $config['after_commit'] ?? null
        );
    }
}
