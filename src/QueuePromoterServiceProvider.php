<?php

namespace AbdulmajeedJamaan\QueuePromoter;

use AbdulmajeedJamaan\QueuePromoter\Commands\PromoteWorkCommand;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\ServiceProvider;

class QueuePromoterServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // The Worker's $isDownForMaintenance argument is a callable that the container
        // cannot autowire, so bind the promoting worker explicitly (mirrors how the
        // framework registers its own "queue.worker" binding).
        $this->app->singleton(PromotingWorker::class, fn ($app) => new PromotingWorker(
            $app['queue'],
            $app['events'],
            $app[ExceptionHandler::class],
            fn (): bool => $app->isDownForMaintenance(),
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
