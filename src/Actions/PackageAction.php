<?php

namespace Drmovi\PackageGenerator\Actions;

use Drmovi\PackageGenerator\Contracts\Operation;
use Drmovi\PackageGenerator\Dtos\Configs;
use Drmovi\PackageGenerator\Entities\ComposerFile;
use Drmovi\PackageGenerator\Entities\PhpUnitXmlFile;
use Drmovi\PackageGenerator\Entities\SkaffoldYamlFile;
use Drmovi\PackageGenerator\Enums\OperationTypes;
use Drmovi\PackageGenerator\Factories\FrameworkPackageOperationFactory;
use Drmovi\PackageGenerator\Services\ComposerService;
use Drmovi\PackageGenerator\Utils\FileUtil;

abstract class PackageAction implements Operation
{


    protected OperationTypes $operationType = OperationTypes::UNSPECIFIED;
    protected ComposerFile $rootComposerFile;
    protected Operation $packageOperation;
    protected string $packageAbsolutePath;
    protected string $packageNamespace;
    protected string $packageName;
    protected string $packageRelativePath;
    protected string $sharedPackageName;
    protected string $sharedPackageRelativePath;
    protected string $sharedPackageAbsolutePath;
    protected string $sharedPackageComposerName;
    protected string $sharedPackageNamespace;
    protected PhpUnitXmlFile $rootPhpunitXmlFile;
    protected SkaffoldYamlFile $rootSkaffoldYamlFile;
    protected string $packageSkaffoldYamlFileRelativePath;
    protected string $packageServiceFolderInSharedPackageAbsolutePath;

    public function __construct(
        protected readonly string          $packageComposerName,
        protected readonly Configs         $configs,
        protected readonly ComposerService $composer,
    )
    {
        $this->rootComposerFile = new ComposerFile(getcwd());
        $this->rootPhpunitXmlFile = new PhpunitXmlFile(getcwd());
        $this->rootSkaffoldYamlFile = new SkaffoldYamlFile(getcwd());
        $this->packageName = $this->getPackageName($packageComposerName);
        $this->packageRelativePath = $this->configs->getPackagePath() . DIRECTORY_SEPARATOR . $this->packageName;
        $this->packageAbsolutePath = getcwd() . DIRECTORY_SEPARATOR . $this->packageRelativePath;
        $this->packageNamespace = $this->getPackageNamespace($packageComposerName);
        $this->packageSkaffoldYamlFileRelativePath = $this->packageRelativePath . '/k8s/skaffold.yaml';

        $this->sharedPackageName = 'shared';
        $this->sharedPackageRelativePath = $this->configs->getPackagePath() . DIRECTORY_SEPARATOR . $this->sharedPackageName;
        $this->sharedPackageAbsolutePath = getcwd() . DIRECTORY_SEPARATOR . $this->sharedPackageRelativePath;
        $this->sharedPackageComposerName = $this->configs->getVendorName() . DIRECTORY_SEPARATOR . $this->sharedPackageName;
        $this->sharedPackageNamespace = $this->getPackageNamespace($this->sharedPackageComposerName);

        $this->packageServiceFolderInSharedPackageAbsolutePath = $this->sharedPackageAbsolutePath . '/services/' . ucwords($this->packageName);

        $this->packageOperation = (new FrameworkPackageOperationFactory())->make(
            framework: $this->configs->getFramework(),
            generatorArgs: [
                'packageComposerName' => $this->packageComposerName,
                'packageName' => $this->packageName,
                'packageAbsolutePath' => $this->packageAbsolutePath,
                'packageNamespace' => $this->packageNamespace,
                'rootComposerFile' => $this->rootComposerFile,
                'configs' => $this->configs,
            ],
            operation: $this->operationType,
        );

    }

    public function backup(): void
    {
        $this->rootComposerFile->backup();
        $this->packageOperation->backup();
        $this->rootPhpunitXmlFile->backup();
        $this->rootSkaffoldYamlFile->backup();
    }

    public function rollback(): void
    {
        $this->rootComposerFile->rollback();
        FileUtil::removeDirectory($this->packageAbsolutePath);
        FileUtil::removeDirectory($this->packageServiceFolderInSharedPackageAbsolutePath);
        $this->packageOperation->rollback();
        $this->rootPhpunitXmlFile->rollback();
        $this->rootSkaffoldYamlFile->rollback();
    }

    public function run(): void
    {
        if ($this->isInitialSetup()) {
            $this->init();
            $this->packageOperation->init();
        }
        $this->exec();
    }

    abstract public function init(): void;

    abstract public function exec(): void;

    protected function getPackageName(string $packageComposerName): string
    {
        $data = explode('/', $packageComposerName);
        return $data[1];
    }

    protected function getPackageNamespace(string $packageComposerName): string
    {
        $data = explode('/', $packageComposerName);
        $data = array_map(fn($item) => ucwords($item), $data);
        return implode('\\', $data);
    }

    private function isInitialSetup(): bool
    {
        return !FileUtil::directoryExist($this->sharedPackageAbsolutePath);
    }
}
