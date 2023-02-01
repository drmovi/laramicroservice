<?php

namespace {{PROJECT_NAMESPACE}}\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [

    ];


    public function boot(): void
    {
    }


    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
