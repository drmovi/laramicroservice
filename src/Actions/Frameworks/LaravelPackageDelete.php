<?php

namespace Drmovi\MonorepoGenerator\Actions\Frameworks;

use Drmovi\MonorepoGenerator\Contracts\Operation;
use Drmovi\MonorepoGenerator\Dtos\PackageDto;
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
        return Command::SUCCESS;
    }

    public function backup(): void
    {
        // TODO: Implement backup() method.
    }

    public function rollback(): void
    {
        // TODO: Implement backup() method.
    }
}
