<?php

namespace Drmovi\Larapackager\Providers;

use Drmovi\Larapackager\Console\MicroserviceGenerator;
use Illuminate\Support\ServiceProvider;

class ConsoleServiceProvider extends ServiceProvider
{

    private array $commands = [
        MicroserviceGenerator::class,
    ];

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands($this->commands);
        }
    }
}
