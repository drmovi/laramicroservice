<?php

namespace Drmovi\MonorepoGenerator\Actions;

use Drmovi\MonorepoGenerator\Utils\FileUtil;

class DeletePackageAction extends PackageAction
{


    protected function _exec(): void
    {
        $this->packageOperation->exec();
        $this->removePackageRefInRootSkaffoldYamlFile();
        $this->removePackageRefInPhpUnitXmlFile();
        $this->removePackageFromComposer();
        $this->removePackageFolder();
        $this->removePackageServiceFolderInSharedPackage();
    }


    public function rollback(): void
    {
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

    public function backup(): void
    {
        // TODO: Implement backup() method.
    }
}
