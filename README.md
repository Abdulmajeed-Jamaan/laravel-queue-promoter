# Queue Promoter

A `queue:work`-style daemon that promotes **due delayed and expired reserved jobs** onto Laravel's Redis ready list — so your queue length stays accurate even at zero workers, and your autoscaler can wake them back up.

## The problem

With the Redis driver, delayed jobs live in a `:delayed` sorted set and reserved jobs in `:reserved` — they only move onto the ready list when a worker calls `pop()`. At zero workers, that creates a deadlock:

1. A delayed or reserved job comes due.
2. No worker is running, so nothing calls `pop()` to move it onto the ready list.
3. The ready list stays empty, so `LLEN` (what autoscalers like KEDA read) reports `0`.
4. The autoscaler sees an empty queue and keeps workers at `0` — back to step 2.

The jobs never run.

## The fix

Run one `queue:promote` instance. Each pass promotes due jobs onto the ready list — without reserving or running them — so `LLEN` reflects the real backlog. Your workers can then scale **all the way to zero** and spin up only when there's real work, instead of idling around the clock:

```text
BEFORE · 5 workers kept warm just so the queue never stalls
┌──────────┐┌──────────┐┌──────────┐┌──────────┐┌──────────┐
│ worker 1 ││ worker 2 ││ worker 3 ││ worker 4 ││ worker 5 │
│ CPU ▇▇▇▇ ││ CPU ▇▇▇▇ ││ CPU ▇▇▇▇ ││ CPU ▇▇▇▇ ││ CPU ▇▇▇▇ │
└──────────┘└──────────┘└──────────┘└──────────┘└──────────┘

AFTER · 1 lightweight promoter, workers scaled to zero
┌──────────┐
│ promoter │   worker 1 … 5  →  ✕  (scaled to 0)
│ CPU ▏    │
└──────────┘
```

> [!NOTE]
> Only the Redis driver needs this. The `database`, `sqs`, and `beanstalkd` drivers evaluate due-ness at `pop()` time, so they have nothing to promote.

## Installation

```bash
composer require abdulmajeed-jamaan/laravel-queue-promoter
```

The service provider is auto-discovered — no configuration to publish.

## Usage

`queue:promote` mirrors `queue:work`'s signature and options:

```bash
php artisan queue:promote                                # default connection & queue
php artisan queue:promote redis --queue=high,default     # specific connection & queues
php artisan queue:promote redis --once                   # single pass (scheduler / pre-scale hook)
php artisan queue:promote redis --sleep=1 --max-time=3600 # tune the loop
```

Pointing it at a non-Redis connection fails fast instead of silently doing nothing.

> [!IMPORTANT]
> Like `queue:work`, the promoter only touches the queues you name with `--queue`. Be sure to list **every** queue you dispatch delayed jobs to — a due job on an unlisted queue will never be promoted.

A single instance is enough. Run it under Supervisor, systemd, or Kubernetes — it handles `SIGTERM` and `queue:restart` gracefully, so set the termination grace period comfortably above `--sleep`. Or let the scheduler own the loop:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('queue:promote redis --once')->everyFifteenSeconds();
```

## How it works

It runs Laravel's **stock** `queue:work` worker against a Redis connection that promotes instead of reserves. `RedisQueue::pop()` already migrates due delayed and expired reserved jobs onto the ready list *before* it reserves one, so a `PromotingRedisQueue` simply skips the reserve step. The command wires this up via a throwaway connection (a copy of your config) using public APIs only, so your real `redis` connection and live workers are untouched.

## Testing

```bash
composer test
```

Runs against a real Redis-compatible server (Redis or Valkey); see [`.github/workflows/tests.yml`](.github/workflows/tests.yml).

## License

Queue Promoter is open-sourced software licensed under the [MIT license](LICENSE.md).
