<?php

namespace Drmovi\PackageGenerator\Actions;

use Drmovi\PackageGenerator\Contracts\Operation;
use Drmovi\PackageGenerator\Dtos\Configs;
use Drmovi\PackageGenerator\Entities\ComposerFile;
use Drmovi\PackageGenerator\Enums\OperationTypes;
use Drmovi\PackageGenerator\Factories\FrameworkPackageOperationFactory;
use Drmovi\PackageGenerator\Services\ComposerService;
use Drmovi\PackageGenerator\Utils\FileUtil;

class PackageGeneration implements Operation
{

    private ComposerFile $rootComposerFile;
    private Operation $packageOperation;
    private string $packageAbsolutePath;
    private string $packageNamespace;
    private string $packageName;
    private string $packageRelativePath;

    public function __construct(
        private readonly string          $packageComposerName,
        private readonly Configs         $configs,
        private readonly ComposerService $composer,
    )
    {
        $this->rootComposerFile = new ComposerFile(getcwd());
        $this->packageName = $this->getPackageName($packageComposerName);
        $this->packageRelativePath = $this->configs->getPackagePath() . DIRECTORY_SEPARATOR . $this->packageName;
        $this->packageAbsolutePath = getcwd() . DIRECTORY_SEPARATOR . $this->packageRelativePath;
        $this->packageNamespace = $this->getPackageNamespace($packageComposerName);
        $this->packageOperation = (new FrameworkPackageOperationFactory())->make(
            framework: $this->configs->getFramework(),
            generatorArgs: [
                'packageComposerName' => $this->packageComposerName,
                'packageName' => $this->packageName,
                'packageAbsolutePath' => $this->packageAbsolutePath,
                'packageNamespace' => $this->packageNamespace,
                'configs' => $this->configs,
            ],
            operation: OperationTypes::PackageGenerator
        );

    }

    public function backup(): void
    {
        $this->rootComposerFile->backup();
        $this->packageOperation->backup();
    }

    public function exec(): void
    {
        $this->createMainPackage();
        $this->createSharedPackage();
        $this->addPackageSharedFolderToSharedPackage();
        $this->packageOperation->exec();
    }

    public function rollback(): void
    {
        $this->rootComposerFile->rollback();
        FileUtil::removeDirectory($this->packageAbsolutePath);
        $this->packageOperation->rollback();
    }

    private function copyStubFiles(string $source, string $destination, string $composerName, string $packageNamespace, string $packageName): void
    {
        FileUtil::copyDirectory(
            source: __DIR__ . DIRECTORY_SEPARATOR . '/../../stubs' . DIRECTORY_SEPARATOR . $source,
            destination: $destination,
            replacements: [
                '{{PROJECT_COMPOSER_NAME}}' => $composerName,
                '{{PROJECT_VERSION}}' => '1.0.0',
                '{{PROJECT_DESCRIPTION}}' => 'This is a package generated by drmovi PHP Package Generator',
                '{{PROJECT_COMPOSER_NAMESPACE}}' => str_replace('\\', '\\\\', $packageNamespace),
                '{{PROJECT_NAMESPACE}}' => $packageNamespace,
                '{{PROJECT_CLASS_NAME}}' => ucwords($packageName),
                '{{PROJECT_FILE_NAME}}' => strtolower($packageName),
            ]);
    }

    private function getPackageName(string $packageComposerName): string
    {
        $data = explode('/', $packageComposerName);
        return $data[1];
    }

    private function getPackageNamespace(string $packageComposerName): string
    {
        $data = explode('/', $packageComposerName);
        $data = array_map(fn($item) => ucwords($item), $data);
        return implode('\\', $data);
    }


    private function createPackage(
        string   $destination,
        string   $composerName,
        string   $packageNamespace,
        string   $packageName,
        string   $packageRelativePath,
        callable $beforeComposerCommand = null
    ): void
    {
        $this->copyStubFiles(
            source: 'frameworks' . DIRECTORY_SEPARATOR . $this->configs->getFramework() . DIRECTORY_SEPARATOR . 'package',
            destination: $destination,
            composerName: $composerName,
            packageNamespace: $packageNamespace,
            packageName: $packageName
        );
        if (is_callable($beforeComposerCommand)) {
            call_user_func($beforeComposerCommand, ...func_get_args());
        }
        $this->composer->runComposerCommand([
            'config',
            "repositories.{$packageName}",
            json_encode(['type' => 'path', 'url' => './' . $packageRelativePath]),
            '--no-interaction'
        ]);
        $this->composer->runComposerCommand([
            'require',
            $composerName,
            '--no-interaction',
            '--no-install'
        ]);
    }

    private function createMainPackage(): void
    {
        $this->createPackage(
            destination: $this->packageAbsolutePath,
            composerName: $this->packageComposerName,
            packageNamespace: $this->packageNamespace,
            packageName: $this->packageName,
            packageRelativePath: $this->packageRelativePath
        );
    }

    private function createSharedPackage(): void
    {
        $packageName = 'shared';
        $packageRelativePath = $this->configs->getPackagePath() . DIRECTORY_SEPARATOR . $packageName;
        $packageAbsolutePath = getcwd() . DIRECTORY_SEPARATOR . $packageRelativePath;
        $packageComposerName = $this->configs->getVendorName() . '/' . $packageName;
        $packageNamespace = $this->getPackageNamespace($packageComposerName);
        if (FileUtil::directoryExist($packageAbsolutePath)) {
            return;
        }
        $this->createPackage(
            destination: $packageAbsolutePath,
            composerName: $packageComposerName,
            packageNamespace: $packageNamespace,
            packageName: $packageName,
            packageRelativePath: $packageRelativePath,
            beforeComposerCommand: [$this, 'addServicePsr4NamespaceToSharedPackageComposerFile']
        );
        $this->copyStubFiles(
            source: 'frameworks' . DIRECTORY_SEPARATOR . $this->configs->getFramework() . DIRECTORY_SEPARATOR . 'shared/app',
            destination: $packageAbsolutePath . DIRECTORY_SEPARATOR . 'app',
            composerName: $packageComposerName,
            packageNamespace: $this->getPackageNamespace($packageComposerName),
            packageName: $packageName
        );
        $this->copyStubFiles(
            source: 'frameworks' . DIRECTORY_SEPARATOR . $this->configs->getFramework() . DIRECTORY_SEPARATOR . 'shared/routes',
            destination: $packageAbsolutePath . DIRECTORY_SEPARATOR . 'routes',
            composerName: $packageComposerName,
            packageNamespace: $this->getPackageNamespace($packageComposerName),
            packageName: $packageName
        );
    }

    private function addPackageSharedFolderToSharedPackage()
    {

        $packageAbsolutePath = getcwd() . DIRECTORY_SEPARATOR . $this->configs->getPackagePath() . DIRECTORY_SEPARATOR . 'shared';
        $this->copyStubFiles(
            'frameworks' . DIRECTORY_SEPARATOR . $this->configs->getFramework() . DIRECTORY_SEPARATOR . 'shared/services',
            destination: $packageAbsolutePath . DIRECTORY_SEPARATOR . 'services',
            composerName: $this->packageComposerName,
            packageNamespace: $this->getPackageNamespace($this->configs->getVendorName() . '/shared'),
            packageName: $this->packageName
        );
    }

    private function addServicePsr4NamespaceToSharedPackageComposerFile(
        string $destination,
        string $composerName,
        string $packageNamespace,
        string $packageName,
        string $packageRelativePath,
    )
    {
        $file = new ComposerFile($destination);
        $file->addPsr4Namespace($packageNamespace . '\\Services', 'services/');
    }

}
