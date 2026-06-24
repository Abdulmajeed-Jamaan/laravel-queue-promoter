<?php

use AbdulmajeedJamaan\QueuePromoter\Commands\PromoteWorkCommand;
use AbdulmajeedJamaan\QueuePromoter\PromotingWorker;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Queue\Queue;
use Illuminate\Queue\RedisQueue;

/**
 * Invoke the worker's protected getNextJob() the way the daemon loop does.
 */
function runPromotionPass(PromotingWorker $worker, Queue $connection, string $queue): mixed
{
    return (fn ($c, $q) => $this->getNextJob($c, $q))->call($worker, $connection, $queue);
}

function makePromotingWorker(): PromotingWorker
{
    return new PromotingWorker(
        app('queue'),
        app('events'),
        app(ExceptionHandler::class),
        fn (): bool => false,
    );
}

it('migrates due delayed and reserved jobs for each queue, then returns null', function () {
    $redisQueue = Mockery::mock(RedisQueue::class);
    $redisQueue->shouldReceive('getConnectionName')->andReturn('redis');

    foreach (['default', 'high'] as $queue) {
        $prefixed = "queues:{$queue}";
        $redisQueue->shouldReceive('getQueue')->with($queue)->andReturn($prefixed);
        $redisQueue->shouldReceive('migrateExpiredJobs')
            ->with("{$prefixed}:delayed", $prefixed)->once();
        $redisQueue->shouldReceive('migrateExpiredJobs')
            ->with("{$prefixed}:reserved", $prefixed)->once();
    }

    $result = runPromotionPass(makePromotingWorker(), $redisQueue, 'default,high');

    expect($result)->toBeNull();
});

it('defensively no-ops if handed a non-redis connection', function () {
    $connection = Mockery::mock(Queue::class);
    $connection->shouldReceive('getConnectionName')->andReturn('database');
    $connection->shouldNotReceive('migrateExpiredJobs');

    $result = runPromotionPass(makePromotingWorker(), $connection, 'default');

    expect($result)->toBeNull();
});

it('fails fast when the connection is not backed by redis', function () {
    $this->artisan('queue:promote', ['connection' => 'sync'])
        ->assertFailed();
});

it('registers queue:promote backed by the promoting worker', function () {
    $command = app(PromoteWorkCommand::class);

    expect($command->getName())->toBe('queue:promote');
});
