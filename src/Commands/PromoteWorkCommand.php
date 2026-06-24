<?php

namespace AbdulmajeedJamaan\QueuePromoter\Commands;

use AbdulmajeedJamaan\QueuePromoter\PromotingRedisConnector;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Queue\Console\WorkCommand;
use Illuminate\Queue\QueueManager;
use Illuminate\Queue\Worker;

class PromoteWorkCommand extends WorkCommand
{
    protected $description = 'Continuously promote due delayed and expired reserved jobs onto the ready queue (Redis queues only).';

    public function __construct(Worker $worker, Cache $cache)
    {
        // Reuse queue:work's signature with the stock worker, then just rename.
        parent::__construct($worker, $cache);

        $this->setName('queue:promote');
    }

    /**
     * Run a stock worker against a throwaway connection that mirrors the target's
     * config but promotes instead of reserving. A fresh name keeps this on public
     * APIs and leaves the real redis connection (and live workers) untouched.
     */
    protected function runWorker($connection, $queue)
    {
        /** @var \Illuminate\Contracts\Config\Repository $config */
        $config = $this->laravel['config'];

        if ($config->get("queue.connections.{$connection}.driver") !== 'redis') {
            $this->components->error(
                "The [{$connection}] queue connection is not backed by Redis; queue:promote only supports Redis queues."
            );

            return self::FAILURE;
        }

        /** @var \Illuminate\Contracts\Redis\Factory $redis */
        $redis = $this->laravel['redis'];

        /** @var QueueManager $manager */
        $manager = $this->laravel['queue'];

        $promoteConnection = "{$connection}:promoter";

        $manager->addConnector('redis-promoter', fn () => new PromotingRedisConnector($redis, $connection));

        $config->set(
            "queue.connections.{$promoteConnection}",
            array_merge((array) $config->get("queue.connections.{$connection}"), ['driver' => 'redis-promoter'])
        );

        return parent::runWorker($promoteConnection, $queue);
    }
}
