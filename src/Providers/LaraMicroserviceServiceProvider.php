<?php

namespace Drmovi\LaraMicroservice\Providers;

use Illuminate\Support\ServiceProvider;

class LaraMicroserviceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../configs/laramicroservice.php', 'laramicroservice');
    }
}
