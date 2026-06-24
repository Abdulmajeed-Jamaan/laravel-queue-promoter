# Laravel Queue Promoter

A `queue:work`-style daemon that **promotes due delayed and expired reserved jobs onto the ready queue** for Laravel's Redis queue driver.

With the Redis driver, delayed jobs live in a `:delayed` sorted set and reserved jobs in a `:reserved` sorted set. They only move onto the ready list when a worker calls `pop()`. If you scale your workers to zero, due jobs stay invisible — `LLEN` (the metric most autoscalers read) reports nothing, so nothing scales back up. This package runs that promotion step on its own, so the ready queue always reflects the real backlog.

> Only the Redis driver needs this. The `database`, `sqs`, and `beanstalkd` drivers evaluate due-ness at `pop()` time, so they have nothing to promote.

## How it works

It runs Laravel's **stock** `queue:work` worker, unchanged, against a Redis connection that promotes instead of reserves. `RedisQueue::pop()` already migrates due delayed and expired reserved jobs onto the ready list *before* it reserves one; a `PromotingRedisQueue` simply overrides the reserve step to return nothing, so each pass promotes but hands the worker no job.

The `queue:promote` command wires this up entirely through public APIs: it registers a `redis-promoter` connector and points a throwaway connection — a copy of your real connection's config — at it, then runs the stock worker against that. Your actual `redis` connection is never modified, so real `queue:work` workers are unaffected. Everything else — the daemon loop, `--sleep`, signal handling, `queue:restart`, pause/resume, `--memory`/`--max-time` — is the framework's own behaviour, untouched. (The promoting queue reports your real connection's name, so `queue:pause`/`queue:restart` and pop events resolve correctly.)

## Installation

```bash
composer require abdulmajeed-jamaan/laravel-queue-promoter
```

The service provider is auto-discovered.

## Usage

`queue:promote` mirrors `queue:work`'s signature:

```bash
# promote the default Redis connection's default queue, looping every 3s
php artisan queue:promote

# a specific connection and queues
php artisan queue:promote redis --queue=high,default

# run a single pass (for a scheduler or a pre-scale hook)
php artisan queue:promote redis --once

# tune the loop
php artisan queue:promote redis --sleep=1 --max-time=3600
```

Pointing it at a non-Redis connection fails fast:

```
The [database] queue connection is not backed by Redis; queue:promote only supports Redis queues.
```

### Running it

Run one replica alongside your workers (a single instance is enough):

```bash
php artisan queue:promote redis --queue=high,default --sleep=1
```

It handles `SIGTERM` gracefully, so it's safe to deploy as a long-running process under Supervisor, systemd, or Kubernetes. Set a `terminationGracePeriodSeconds`/`stopwaitsecs` comfortably above `--sleep`.

Or, if you prefer the scheduler to own the loop, schedule the single-pass form:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('queue:promote redis --once')->everyFifteenSeconds();
```

## Testing

```bash
composer test
```

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md).
