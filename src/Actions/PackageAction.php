<?php

namespace Drmovi\MonorepoGenerator\Actions;

use Drmovi\MonorepoGenerator\Contracts\Operation;
use Drmovi\MonorepoGenerator\Dtos\ActionDto;
use Drmovi\MonorepoGenerator\Dtos\PackageData;
use Drmovi\MonorepoGenerator\Dtos\PackageDto;
use Drmovi\MonorepoGenerator\Factories\FrameworkOperationFactory;
use Drmovi\MonorepoGenerator\Services\ComposerFileService;
use Drmovi\MonorepoGenerator\Services\PhpstanNeonService;
use Drmovi\MonorepoGenerator\Services\PhpUnitXmlFileService;
use Drmovi\MonorepoGenerator\Services\SkaffoldYamlFileService;
use Symfony\Component\Console\Command\Command;

abstract class PackageAction implements Operation
{


    protected PackageData $packageData;

    public function __construct(protected readonly ActionDto $actionDto)
    {
        $this->packageData = new PackageData(
            rootComposerFileService: new ComposerFileService(getcwd(), $this->actionDto->composerService),
            rootPhpunitXmlFileService: new PhpUnitXmlFileService(getcwd()),
            rootSkaffoldYamlFileService: new SkaffoldYamlFileService(getcwd()),
            phpstanNeonFileService: new PhpstanNeonService(getcwd() . DIRECTORY_SEPARATOR . $this->actionDto->configs->getConfPath()),
            packageName: $packageName = $this->actionDto->input->getArgument('name'),
            packageRelativePath: $packageRelativePath = ($this->isSharedPackage() ? $this->actionDto->configs->getSharedPackagesPath() : $this->actionDto->configs->getPackagesPath()) . DIRECTORY_SEPARATOR . $packageName,
            packageAbsolutePath: getcwd() . DIRECTORY_SEPARATOR . $packageRelativePath,
            packageNamespace: $this->getPackageNamespace($packageName),
            packageSkaffoldYamlFileRelativePath: $packageRelativePath . '/k8s/skaffold.yaml',
            packageComposerName: $this->getComposerPackageName(),
            isSharedPackage: $this->isSharedPackage()
        );

    }

    public function exec(): int
    {
        $frameworkPackageOperation = (new FrameworkOperationFactory())->make(PackageDto::loadFromActionDto($this->actionDto, $this->packageData));
        $this->backup();
        $frameworkPackageOperation->backup();
        try {
            $this->_exec();
            $frameworkPackageOperation->exec();
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $frameworkPackageOperation->rollback();
            $this->rollback();
            $this->actionDto->io->error($e->getMessage());
            return Command::FAILURE;
        }
    }


    abstract protected function _exec(): void;

    abstract public function backup(): void;

    abstract public function rollback(): void;


    private function getComposerPackageName(): string
    {
        return "{$this->actionDto->configs->getVendorName()}/{$this->packageData->packageName}";
    }

    private function isSharedPackage(): bool
    {
        return $this->actionDto->input->getArgument('shared');
    }

    protected function getPackageNamespace(string $packageName): string
    {
        return implode('\\', [
            ucwords($this->actionDto->configs->getVendorName()),
            ucwords($packageName)
        ]);
    }

}
