<?php

namespace Drmovi\LaraMicroservice\Providers;

use Drmovi\LaraMicroservice\Console\MicroserviceGenerator;
use Drmovi\LaraMicroservice\Console\MicroserviceRemover;
use Illuminate\Support\ServiceProvider;

class ConsoleServiceProvider extends ServiceProvider
{

    private array $commands = [
        MicroserviceGenerator::class,
        MicroserviceRemover::class
    ];

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands($this->commands);
        }
    }
}
