<?php

namespace {{PACKAGE_NAMESPACE}}\Providers;

use Illuminate\Support\ServiceProvider;

class {{PACKAGE_CLASS_NAME}}ServiceProvider extends ServiceProvider
{

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/{{PACKAGE_FILE_NAME}}.php', '{{PACKAGE_FILE_NAME}}');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../../lang', '{{PACKAGE_FILE_NAME}}');

    }
}
