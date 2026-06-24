<?php

use AbdulmajeedJamaan\QueuePromoter\Tests\TestCase;

uses(TestCase::class)->in('Feature');

/**
 * Number of jobs sitting on a queue's ready list — the length autoscalers read
 * (LLEN) and the metric this package exists to keep accurate. Resolved through
 * the queue's own key naming so it stays correct regardless of prefix config.
 */
function readyJobCount(string $queue = 'default'): int
{
    $connection = app('queue')->connection('redis');

    return (int) app('redis')->connection()->llen($connection->getQueue($queue));
}
