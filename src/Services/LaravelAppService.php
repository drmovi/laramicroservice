<?php

namespace Drmovi\MonorepoGenerator\Services;

use Drmovi\MonorepoGenerator\Dtos\ActionDto;

class LaravelAppService
{

    private mixed $app;

    private static ?self  $instance = null;

    private function __construct(private readonly ActionDto $actionDto)
    {
        $this->app = require $this->actionDto->configs->getAppPath() . '/bootstrap/app.php';
        $this->app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
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
        $this->app->artisan($command, $args);
    }
}
