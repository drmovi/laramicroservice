<?php

namespace Drmovi\LaraMicroservice\Providers;

use Drmovi\LaraMicroservice\Console\MicroserviceGenerator;
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
