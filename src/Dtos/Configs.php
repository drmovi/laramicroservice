<?php

namespace Drmovi\PackageGenerator\Dtos;

use Composer\Console\Application;

class Configs
{
    private string $mode;

    private ?string $namePrefix = null;

    private string $appPath;

    private string $packagePath;

    private function __construct()
    {
    }

    public static function loadFromComposer(Application $composer): self
    {
        $data = $composer->getComposer()->getLocker()->getLockData();
        $instance = new self();
        $instance->mode = $data['extra']['monorepo']['mode'] ?? 'package';
        $instance->appPath = $data['extra']['monorepo']['app_path'] ?? 'app';
        $instance->namePrefix = $data['extra']['monorepo']['app_path'] ?? null;
        $instance->packagePath = $data['extra']['monorepo']['package_path'] ?? 'packages';
        return $instance;
    }

    public function getMode(): string
    {
        return $this->mode;
    }

    public function getAppPath(): string
    {
        return $this->appPath;
    }

    public function getNamePrefix(): ?string
    {
        return $this->namePrefix;
    }

    public function getPackagePath(): string
    {
        return $this->packagePath;
    }


}
