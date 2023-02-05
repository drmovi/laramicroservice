<?php

namespace Drmovi\MonorepoGenerator\Services;

use Drmovi\MonorepoGenerator\Dtos\PackageDto;

class LaravelAppService
{

    private mixed $app;

    private static self $instance;

    private function __construct(private readonly PackageDto $actionDto)
    {
        $app = require $this->actionDto->configs->getAppPath() . '/bootstrap/app.php';
        $this->app = $app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
    }

    public static function instance(PackageDto $actionDto): self
    {
        if (!self::$instance) {
            self::$instance = new self($actionDto);
        }
        return self::$instance;
    }

    public function artisan(string $command, array $args = []): void
    {
        $this->app->call($command, $args);
    }
}
