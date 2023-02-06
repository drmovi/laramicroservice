<?php

namespace Drmovi\MonorepoGenerator\Actions;

use Drmovi\MonorepoGenerator\Enums\ConstData;
use Drmovi\MonorepoGenerator\Enums\Modes;
use Drmovi\MonorepoGenerator\Services\ComposerFileService;
use Drmovi\MonorepoGenerator\Utils\FileUtil;

class CreatePackageAction extends PackageAction
{

    protected function _exec(): void
    {
        $this->createPackage();
        $this->addExtraDataToApiPackage();
        $this->createK8sFiles();
        $this->addPackageSkaffoldFileToRootSkaffold();
    }

    private function createPackage(): void
    {
        $this->copyStubFiles(
            source: 'frameworks/' . $this->actionDto->configs->getFramework() . '/package',
            destination: $this->packageData->packageAbsolutePath,
            composerName: $this->packageData->packageComposerName,
            packageNamespace: $this->packageData->packageNamespace,
            packageName: $this->packageData->packageName,
            appPath: $this->actionDto->configs->getAppPath(),
            packagePath: $this->actionDto->configs->getPackagesPath()
        );
        $this->packageData->rootComposerFileService->runComposerCommand([
            'config',
            "repositories.{$this->packageData->packageName}",
            json_encode(['type' => 'path', 'url' => './' . $this->packageData->packageRelativePath]),
            '--no-interaction'
        ]);
        $this->packageData->rootComposerFileService->runComposerCommand([
            'require',
            $this->packageData->packageComposerName,
        ]);
    }


    private function createK8sFiles(): void
    {
        if (!$this->canAddK8s()) {
            return;
        }
        $this->copyStubFiles(
            source: 'devops/package/k8s',
            destination: $this->packageData->packageAbsolutePath . '/k8s',
            composerName: $this->packageData->packageComposerName,
            packageNamespace: $this->packageData->packageNamespace,
            packageName: $this->packageData->packageName,
            appPath: $this->actionDto->configs->getAppPath(),
            packagePath: $this->actionDto->configs->getPackagesPath()

        );
    }

    private function addPackageSkaffoldFileToRootSkaffold(): void
    {
        if (!$this->canAddK8s()) {
            return;
        }
        $this->packageData->rootSkaffoldYamlFileService->addRequire($this->packageData->packageSkaffoldYamlFileRelativePath);
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
        FileUtil::removeDirectory($this->packageData->packageAbsolutePath);
    }

    private function canAddK8s(): bool
    {
        return $this->actionDto->configs->getMode() !== Modes::MICROSERVICE->value || $this->packageData->isSharedPackage;
    }


    private function addExtraDataToApiPackage(): void
    {
        if (!$this->packageData->isSharedPackage) {
            return;
        }
        $apiPackageAbsolutePath = $this->actionDto->configs->getSharedPackagesPath() . DIRECTORY_SEPARATOR . ConstData::API_PACKAGE_NAME->value;
        $this->copyStubFiles(
            source: "frameworks/{$this->actionDto->configs->getFramework()}/shared/services",
            destination: $apiPackageAbsolutePath . '/services',
            composerName: $this->packageData->packageComposerName,
            packageNamespace: $this->packageData->packageNamespace,
            packageName: $this->packageData->packageName,
            appPath: $this->actionDto->configs->getAppPath(),
            packagePath: $this->actionDto->configs->getPackagesPath()

        );
        if (ConstData::API_PACKAGE_NAME->value !== $this->packageData->packageName) {
            return;
        }
        (new ComposerFileService($apiPackageAbsolutePath, $this->actionDto->composerService))
            ->addPsr4Namespace([
                $this->getPackageNamespace(ConstData::API_PACKAGE_NAME->value).'\Services\\' => $this->actionDto->configs->getSharedPackagesPath() . DIRECTORY_SEPARATOR . ConstData::API_PACKAGE_NAME->value . '/services/'
            ]);
    }
}
