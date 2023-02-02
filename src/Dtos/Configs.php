<?php

namespace Drmovi\PackageGenerator\Dtos;

use Composer\Console\Application;

class Configs
{
    private string $mode;

    private ?string $vendorName;

    private string $appPath;

    private string $packagePath;

    private string $framework;

    private function __construct()
    {
    }

    public static function loadFromComposer(Application $composer): self
    {
        $data = json_decode(file_get_contents($composer->getComposer()->getConfig()->getConfigSource()->getName()), true);
        $instance = new self();
        $instance->mode = $data['extra']['monorepo']['mode'] ?? 'package';
        $instance->appPath = $data['extra']['monorepo']['app_path'] ?? 'app';
        $instance->vendorName = $data['extra']['monorepo']['vendor_name'] ?? null;
        $instance->packagePath = $data['extra']['monorepo']['package_path'] ?? 'packages';
        $instance->framework = $data['extra']['monorepo']['framework'] ?? null;
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

    public function getVendorName(): ?string
    {
        return $this->vendorName;
    }

    public function getPackagePath(): string
    {
        return $this->packagePath;
    }

    public function getFramework(): string
    {
        return $this->framework;
    }


}
