<?php

namespace AbdulmajeedJamaan\QueuePromoter\Tests;

use AbdulmajeedJamaan\QueuePromoter\QueuePromoterServiceProvider;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * @param  Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            QueuePromoterServiceProvider::class,
        ];
    }

    /**
     * Point the suite at a real Redis-backed queue connection so the integration
     * tests can exercise the actual migrate-on-promote behaviour. Uses the phpredis
     * extension; a dedicated database lets the integration tests flush freely
     * without touching anything else.
     *
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $config = $app['config'];

        $config->set('database.redis.client', env('REDIS_CLIENT', 'phpredis'));
        $config->set('database.redis.default', [
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'port' => (int) env('REDIS_PORT', 6379),
            'database' => (int) env('REDIS_DB', 5),
        ]);

        $config->set('queue.default', 'redis');
        $config->set('queue.connections.redis', [
            'driver' => 'redis',
            'connection' => 'default',
            'queue' => 'default',
            'retry_after' => 90,
            'block_for' => null,
        ]);
    }
}
