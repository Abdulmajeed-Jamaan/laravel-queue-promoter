<?php

namespace AbdulmajeedJamaan\QueuePromoter\Commands;

use AbdulmajeedJamaan\QueuePromoter\PromotingWorker;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Queue\Console\WorkCommand;
use Illuminate\Queue\RedisQueue;

class PromoteWorkCommand extends WorkCommand
{
    protected $description = 'Continuously promote due delayed and expired reserved jobs onto the ready queue (Redis queues only).';

    public function __construct(PromotingWorker $worker, Cache $cache)
    {
        // Reuse queue:work's full argument/option definition (parsed from the
        // inherited $signature) verbatim, then just rename the command.
        parent::__construct($worker, $cache);

        $this->setName('queue:promote');
    }

    /**
     * Validate the connection is Redis before starting the loop, so misconfiguration
     * fails fast with a clear message instead of silently promoting nothing.
     */
    protected function runWorker($connection, $queue)
    {
        if (! $this->laravel['queue']->connection($connection) instanceof RedisQueue) {
            $this->components->error(
                "The [{$connection}] queue connection is not backed by Redis; queue:promote only supports Redis queues."
            );

            return self::FAILURE;
        }

        return parent::runWorker($connection, $queue);
    }
}
