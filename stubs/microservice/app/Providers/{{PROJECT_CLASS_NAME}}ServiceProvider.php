<?php

namespace {{PROJECT_NAMESPACE}}\Providers;

use Illuminate\Support\ServiceProvider;

class {{PROJECT_CLASS_NAME}}ServiceProvider extends ServiceProvider
{

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/{{PROJECT_FILE_NAME}}.php', '{{PROJECT_FILE_NAME}}');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../../lang', '{{PROJECT_FILE_NAME}}');

    }
}
