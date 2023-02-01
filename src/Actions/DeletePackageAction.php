<?php

namespace Drmovi\PackageGenerator\Actions;

use Drmovi\PackageGenerator\Utils\FileUtil;

class DeletePackageAction extends PackageAction
{

    public function exec(): void
    {
        $this->removePackageRefInRootSkaffoldYamlFile();
        $this->removePackageRefInPhpUnitXmlFile();
        $this->removePackageFromComposer();
        $this->removePackageFolder();
        $this->removePackageServiceFolderInSharedPackage();
    }

    public function rollback(): void
    {
        parent::rollback();
        $this->composer->runComposerCommand([
            'install',
            '--no-interaction',
        ]);
    }

    private function removePackageFolder(): void
    {
        FileUtil::removeDirectory($this->packageAbsolutePath);
    }

    private function removePackageRefInRootSkaffoldYamlFile(): void
    {
        $this->rootSkaffoldYamlFile->removeRequire($this->packageSkaffoldYamlFileRelativePath);
    }

    private function removePackageRefInPhpUnitXmlFile(): void
    {
        $this->rootPhpunitXmlFile->removeTestDirectories($this->packageRelativePath);
    }

    private function removePackageFromComposer(): void
    {
        $this->composer->runComposerCommand([
            'remove',
            $this->packageComposerName,
            '--no-interaction',
        ]);
        $this->composer->runComposerCommand([
            'config',
            'repositories.' . $this->packageName,
            '--unset',
            '--no-interaction',
        ]);
    }

    private function removePackageServiceFolderInSharedPackage(): void
    {
        FileUtil::removeDirectory($this->packageServiceFolderInSharedPackageAbsolutePath);
    }
}
