<?php

namespace AbdulmajeedJamaan\QueuePromoter\Tests\Fixtures;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * A minimal real queued job. It records, in-process, that it was handled so a
 * test can assert a promoted job is actually picked up and run by a worker.
 */
class RecordingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /**
     * Allow more than one attempt: reclaiming an expired reservation counts as a
     * retry, so a stuck-then-promoted job is on its second attempt when it runs.
     */
    public int $tries = 3;

    /** @var list<string> queues whose job has been handled this process */
    public static array $handled = [];

    public function __construct(public string $tag = 'default') {}

    public static function reset(): void
    {
        static::$handled = [];
    }

    public function handle(): void
    {
        static::$handled[] = $this->tag;
    }
}
