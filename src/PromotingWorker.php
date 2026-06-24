<?php

namespace AbdulmajeedJamaan\QueuePromoter;

use Illuminate\Contracts\Queue\Queue;
use Illuminate\Queue\RedisQueue;
use Illuminate\Queue\Worker;

class PromotingWorker extends Worker
{
    /**
     * Promote due jobs for each queue instead of reserving one to process.
     *
     * Returning null makes the daemon treat every pass as "no job available", so the
     * worker reuses its sleep, signal handling, restart, pause, memory, and max-time
     * machinery wholesale while never actually processing a job.
     *
     * @param  Queue  $connection
     * @param  string  $queue
     * @return null
     */
    protected function getNextJob($connection, $queue)
    {
        foreach (explode(',', $queue) as $name) {
            if ($this->queuePaused($connection->getConnectionName(), $name)) {
                continue;
            }

            $this->promote($connection, $name);
        }

        return null;
    }

    /**
     * Promote a queue's due delayed and expired reserved jobs onto its ready list.
     *
     * Migration is unique to the Redis driver; the command validates the connection
     * up front, so this instanceof guard is defensive (and narrows the type).
     */
    private function promote(Queue $connection, string $queue): void
    {
        if (! $connection instanceof RedisQueue) {
            return;
        }

        $prefixed = $connection->getQueue($queue);

        $connection->migrateExpiredJobs($prefixed.':delayed', $prefixed);
        $connection->migrateExpiredJobs($prefixed.':reserved', $prefixed);
    }
}
