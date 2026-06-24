<?php

use AbdulmajeedJamaan\QueuePromoter\PromotingRedisConnector;
use AbdulmajeedJamaan\QueuePromoter\PromotingRedisQueue;
use AbdulmajeedJamaan\QueuePromoter\Tests\Fixtures\RecordingJob;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;

/**
 * These tests talk to a real Redis-compatible server (Valkey/Redis). They fail
 * hard if no server is reachable — these are integration tests, so a missing
 * connection is a failure, not a reason to skip.
 */
beforeEach(function () {
    try {
        app('redis')->connection()->flushdb();
    } catch (Throwable $e) {
        $this->fail('Redis/Valkey is not reachable; integration tests require a running server. '.$e->getMessage());
    }

    RecordingJob::reset();
});

it('promotes a due delayed job so a worker can then process it', function () {
    // A job dispatched with a delay that has since come due — exactly the
    // scale-to-zero case: it is sitting in the delayed set, and because no
    // worker is calling pop(), nothing has migrated it onto the ready list.
    RecordingJob::dispatch('default')
        ->onConnection('redis')
        ->onQueue('default')
        ->delay(Carbon::now()->subMinute());

    // The ready list (the length autoscalers read) shows nothing yet, so a
    // worker scaled back up would see an empty queue.
    expect(readyJobCount('default'))->toBe(0);

    // The promoter migrates the due job onto the ready list — no worker needed.
    $this->artisan('queue:promote', [
        'connection' => 'redis',
        '--once' => true,
        '--sleep' => 0,
    ])->assertSuccessful();

    expect(readyJobCount('default'))->toBe(1);

    // And a plain worker now picks it up and runs it, end to end.
    $this->artisan('queue:work', [
        'connection' => 'redis',
        '--once' => true,
        '--queue' => 'default',
    ])->assertSuccessful();

    expect(RecordingJob::$handled)->toBe(['default']);
});

it('promotes an expired reserved job back so it can be retried', function () {
    $queue = app('queue')->connection('redis');

    // A normal ready job.
    RecordingJob::dispatch('default')
        ->onConnection('redis')
        ->onQueue('default');

    expect(readyJobCount('default'))->toBe(1);

    // A worker reserves it (pop) but crashes before deleting it — the job now
    // sits in the reserved set, off the ready list. handle() never ran.
    $queue->pop('default');

    expect(readyJobCount('default'))->toBe(0)
        ->and(RecordingJob::$handled)->toBe([]);

    // retry_after (90s) elapses with no worker running to reclaim the job.
    $this->travel(91)->seconds();

    // The promoter migrates the expired reservation back onto the ready list.
    $this->artisan('queue:promote', [
        'connection' => 'redis',
        '--once' => true,
        '--sleep' => 0,
    ])->assertSuccessful();

    expect(readyJobCount('default'))->toBe(1);

    // A worker can now pick it up and finally run it.
    $this->artisan('queue:work', [
        'connection' => 'redis',
        '--once' => true,
        '--queue' => 'default',
    ])->assertSuccessful();

    expect(RecordingJob::$handled)->toBe(['default']);
});

it('does not promote a paused queue, then resumes once unpaused', function () {
    RecordingJob::dispatch('default')
        ->onConnection('redis')
        ->onQueue('default')
        ->delay(Carbon::now()->subMinute());

    // Pausing writes the cache flag the worker checks; a paused queue must be
    // left untouched, so the due job stays in the delayed set.
    Queue::pause('redis', 'default');

    $this->artisan('queue:promote', [
        'connection' => 'redis',
        '--once' => true,
        '--sleep' => 0,
    ])->assertSuccessful();

    expect(readyJobCount('default'))->toBe(0);

    // Once resumed, the same due job is promoted as normal.
    Queue::resume('redis', 'default');

    $this->artisan('queue:promote', [
        'connection' => 'redis',
        '--once' => true,
        '--sleep' => 0,
    ])->assertSuccessful();

    expect(readyJobCount('default'))->toBe(1);
});

it('connects a promoting redis queue that reports the real connection name', function () {
    $queue = (new PromotingRedisConnector(app('redis'), 'redis'))
        ->connect(config('queue.connections.redis'));

    expect($queue)->toBeInstanceOf(PromotingRedisQueue::class)
        ->and($queue->getConnectionName())->toBe('redis');
});

it('promotes a due job but reserves nothing when popped', function () {
    $promoter = (new PromotingRedisConnector(app('redis'), 'redis'))
        ->connect(config('queue.connections.redis'));

    RecordingJob::dispatch('default')
        ->onConnection('redis')
        ->onQueue('default')
        ->delay(Carbon::now()->subMinute());

    expect(readyJobCount('default'))->toBe(0);

    // pop() migrates the due job onto the ready list, then reserves nothing.
    expect($promoter->pop('default'))->toBeNull()
        ->and(readyJobCount('default'))->toBe(1);
});

it('promotes due jobs across multiple comma-separated queues in one pass', function () {
    RecordingJob::dispatch('high')
        ->onConnection('redis')
        ->onQueue('high')
        ->delay(Carbon::now()->subMinute());

    RecordingJob::dispatch('default')
        ->onConnection('redis')
        ->onQueue('default')
        ->delay(Carbon::now()->subMinute());

    expect(readyJobCount('high'))->toBe(0)
        ->and(readyJobCount('default'))->toBe(0);

    $this->artisan('queue:promote', [
        'connection' => 'redis',
        '--queue' => 'high,default',
        '--once' => true,
        '--sleep' => 0,
    ])->assertSuccessful();

    expect(readyJobCount('high'))->toBe(1)
        ->and(readyJobCount('default'))->toBe(1);
});
