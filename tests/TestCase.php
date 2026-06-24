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
}
