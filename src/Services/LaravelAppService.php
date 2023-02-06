<?php

namespace Drmovi\MonorepoGenerator\Services;

use Drmovi\MonorepoGenerator\Dtos\ActionDto;

class LaravelAppService
{

    private mixed $app;

    private static ?self $instance = null;

    private function __construct(private readonly ActionDto $actionDto)
    {
        $app = require $this->actionDto->configs->getAppPath() . '/bootstrap/app.php';
        $app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
        $this->app = $app;
    }

    public static function instance(ActionDto $actionDto): self
    {
        if (!self::$instance) {
            self::$instance = new self($actionDto);
        }
        return self::$instance;
    }

    public function artisan(string $command, array $args = []): void
    {
        $this->app->make('Illuminate\Contracts\Console\Kernel')->call($command, $args);
    }
}
