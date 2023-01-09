<?php

namespace Drmovi\Larapackager\Providers;

use Drmovi\Larapackager\Console\PackageGenerator;
use Illuminate\Support\ServiceProvider;

class ConsoleServiceProvider extends ServiceProvider
{

    private array $commands = [
        PackageGenerator::class,
    ];

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands($this->commands);
        }
    }
}
