<?php

namespace Drmovi\MonorepoGenerator\Actions\Frameworks;

use Drmovi\MonorepoGenerator\Contracts\Operation;
use Drmovi\MonorepoGenerator\Dtos\PackageDto;
use Drmovi\MonorepoGenerator\Dtos\PhpunitTestSuiteItem;

class LaravelPackageCreate implements Operation
{

    public function __construct(protected readonly PackageDto $packageDto)
    {
    }


    public function exec(): int
    {
        $this->addTestDirectoriesToPhpUnitXml();
    }

    public function backup(): void
    {
        $this->packageDto->packageData->rootPhpunitXmlFileService->backup();
    }

    public function rollback(): void
    {
        $this->packageDto->packageData->rootPhpunitXmlFileService->rollback();
    }

    private function addTestDirectoriesToPhpUnitXml(): void
    {
        $this->packageDto->packageData->rootPhpunitXmlFileService->addTestDirectories(
            new PhpunitTestSuiteItem(
                path: './' . $this->packageDto->packageData->packageRelativePath . '/tests/Unit',
                testSuiteName: 'Unit',
                suffix: 'Test.php'
            ),
            new PhpunitTestSuiteItem(
                path: './' . $this->packageDto->packageData->packageRelativePath . '/tests/Feature',
                testSuiteName: 'Feature',
                suffix: 'Test.php'
            ),
        );
    }
}
