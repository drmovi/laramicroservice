<?php

namespace Drmovi\MonorepoGenerator\Dtos;

use Drmovi\MonorepoGenerator\Services\ComposerFileService;
use Symfony\Component\Console\Input\InputInterface;

class Configs
{
    private ?string $mode = null;

    private ?string $vendorName = null;

    private ?string $appPath = null;

    private ?string $packagesPath = null;

    private ?string $framework = null;

    private ?string $confPath = null;

    private bool $isInitialized = false;

    private ?string $sharedPackagesPath = null;

    private function __construct()
    {
    }

    public static function loadFromComposer(ComposerFileService $composerFileService): self
    {
        $data = $composerFileService->getMonorepoData();
        $instance = new self();
        $instance->mode = $data['mode'] ?? null;
        $instance->appPath = $data['app_path'] ?? null;
        $instance->vendorName = $data['vendor_name'] ?? null;
        $instance->packagesPath = $data['packages_path'] ?? null;
        $instance->sharedPackagesPath = $data['shared_packages_path'] ?? null;
        $instance->framework = $data['framework'] ?? null;
        $instance->isInitialized = (bool)$data;
        return $instance;
    }

    public static function loadFromInput(InputInterface $input): self
    {
        $instance = new self();
        $instance->mode = $input->getArgument('mode');
        $instance->appPath = $input->getArgument('app_path');
        $instance->vendorName = $input->getArgument('vendor_name');
        $instance->packagesPath = $$input->getArgument('packages_path');
        $instance->sharedPackagesPath = $input->getArgument('shared_packages_path');
        $instance->framework = $input->getArgument('framework');
        return $instance;

    }

    public function getMode():? string
    {
        return $this->mode;
    }

    public function getAppPath():? string
    {
        return $this->appPath;
    }

    public function getVendorName(): ?string
    {
        return $this->vendorName;
    }

    public function getPackagesPath():? string
    {
        return $this->packagesPath;
    }

    public function getFramework():? string
    {
        return $this->framework;
    }


    public function getConfPath():? string
    {
        return $this->confPath;
    }


    public function isInitialized(): bool
    {
        return $this->isInitialized;
    }


    public function getSharedPackagesPath(): ?string
    {
        return $this->sharedPackagesPath;
    }


}
