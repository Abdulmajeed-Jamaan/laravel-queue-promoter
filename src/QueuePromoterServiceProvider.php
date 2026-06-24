<?php

namespace AbdulmajeedJamaan\QueuePromoter;

use AbdulmajeedJamaan\QueuePromoter\Commands\PromoteWorkCommand;
use Illuminate\Support\ServiceProvider;

class QueuePromoterServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Build with the stock worker and default cache store, like queue:work.
        $this->app->singleton(PromoteWorkCommand::class, fn ($app) => new PromoteWorkCommand(
            $app['queue.worker'],
            $app['cache.store'],
        ));
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                PromoteWorkCommand::class,
            ]);
        }
    }
}
