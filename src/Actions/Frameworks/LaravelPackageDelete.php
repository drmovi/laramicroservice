<?php

namespace Drmovi\MonorepoGenerator\Actions\Frameworks;

use Drmovi\MonorepoGenerator\Contracts\Operation;
use Drmovi\MonorepoGenerator\Dtos\PackageDto;
use Drmovi\MonorepoGenerator\Dtos\PhpunitTestSuiteItem;
use Drmovi\MonorepoGenerator\Utils\FileUtil;
use Symfony\Component\Console\Command\Command;

class LaravelPackageDelete implements Operation
{

    public function __construct(protected readonly PackageDto $packageDto)
    {
    }

    public function exec(): int
    {
        FileUtil::emptyDirectory($this->packageDto->configs->getAppPath() . '/bootstrap/cache');
        $this->removePackageRefInPhpUnitXmlFile();
        return Command::SUCCESS;
    }

    public function backup(): void
    {
        $this->packageDto->packageData->rootPhpunitXmlFileService->backup();
    }

    public function rollback(): void
    {
        $this->packageDto->packageData->rootPhpunitXmlFileService->rollback();
    }


    private function removePackageRefInPhpUnitXmlFile(): void
    {
        $this->packageDto->packageData->rootPhpunitXmlFileService->removeTestDirectories(
            new PhpunitTestSuiteItem(
                path: './' . $this->packageDto->packageData->packageRelativePath . '/tests/Unit',
                testSuiteName: 'Unit',
                suffix: 'Test.php'
            ),
            new PhpunitTestSuiteItem(
                path: './' . $this->packageDto->packageData->packageRelativePath . '/tests/Feature',
                testSuiteName: 'Feature',
                suffix: 'Test.php'
            )
        );
    }
}
