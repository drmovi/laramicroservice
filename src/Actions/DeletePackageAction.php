<?php

namespace Drmovi\MonorepoGenerator\Actions;

use Drmovi\MonorepoGenerator\Dtos\PackageDto;
use Drmovi\MonorepoGenerator\Enums\ConstData;
use Drmovi\MonorepoGenerator\Factories\FrameworkOperationFactory;
use Drmovi\MonorepoGenerator\Utils\FileUtil;
use Symfony\Component\Console\Command\Command;

class DeletePackageAction extends PackageAction
{

    public function exec(): int
    {
        $frameworkPackageOperation = (new FrameworkOperationFactory())->make(PackageDto::loadFromActionDto($this->actionDto, $this->packageData));
        $this->backup();
        $frameworkPackageOperation->backup();
        try {
            $frameworkPackageOperation->exec();
            $this->_exec();
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->rollback();
            $frameworkPackageOperation->rollback();
            $this->actionDto->io->error($e->getMessage());
            return Command::FAILURE;
        }
    }

    protected function _exec(): void
    {
        $this->removePackageRefInRootSkaffoldYamlFile();
        $this->removePackageFromComposer();
        $this->removePackageFolder();
        $this->removePackageServiceFolderInSharedApiPackage();
    }

    public function backup(): void
    {
        $this->packageData->rootComposerFileService->backup();
        $this->packageData->rootSkaffoldYamlFileService->backup();

    }

    public function rollback(): void
    {
        $this->packageData->rootComposerFileService->rollback();
        $this->packageData->rootSkaffoldYamlFileService->rollback();
        $this->packageData->rootComposerFileService->runComposerCommand([
            'install',
            '--no-interaction',
        ]);
    }

    private function removePackageFolder(): void
    {
        FileUtil::removeDirectory($this->packageData->packageAbsolutePath);
    }

    private function removePackageRefInRootSkaffoldYamlFile(): void
    {
        $this->packageData->rootSkaffoldYamlFileService->removeRequire($this->packageData->packageSkaffoldYamlFileRelativePath);
    }


    private function removePackageFromComposer(): void
    {
        $this->packageData->rootComposerFileService->runComposerCommand([
            'remove',
            $this->packageData->packageComposerName,
        ]);
        $this->packageData->rootComposerFileService->runComposerCommand([
            'config',
            'repositories.' . $this->packageData->packageComposerName,
            '--unset',
            '--no-interaction',
        ]);
    }

    private function removePackageServiceFolderInSharedApiPackage(): void
    {
        FileUtil::removeDirectory($this->actionDto->configs->getSharedPackagesPath() . DIRECTORY_SEPARATOR . ConstData::API_PACKAGE_NAME->value . '/services/' . ucwords($this->packageData->packageName));
    }


}
