<?php

namespace AbdulmajeedJamaan\QueuePromoter;

use Illuminate\Contracts\Redis\Factory as Redis;
use Illuminate\Queue\Connectors\RedisConnector;

/**
 * Builds a {@see PromotingRedisQueue} instead of a standard RedisQueue, reusing
 * the framework's own config handling.
 */
class PromotingRedisConnector extends RedisConnector
{
    /**
     * @param  string|null  $reportAs  real connection name the built queue should report
     */
    public function __construct(Redis $redis, private readonly ?string $reportAs = null, $connection = null)
    {
        parent::__construct($redis, $connection);
    }

    /**
     * @param  array<string, mixed>  $config
     * @return PromotingRedisQueue
     */
    public function connect(array $config)
    {
        return (new PromotingRedisQueue(
            $this->redis,
            $config['queue'],
            $config['connection'] ?? $this->connection,
            $config['retry_after'] ?? 60,
            $config['block_for'] ?? null,
            $config['after_commit'] ?? null,
            $config['migration_batch_size'] ?? -1
        ))->promotingFor($this->reportAs);
    }
}
