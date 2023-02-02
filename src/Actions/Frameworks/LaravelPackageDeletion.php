<?php

namespace Drmovi\PackageGenerator\Actions\Frameworks;

use Drmovi\PackageGenerator\Contracts\Operation;
use Drmovi\PackageGenerator\Dtos\Configs;
use Drmovi\PackageGenerator\Entities\ComposerFile;
use Drmovi\PackageGenerator\Services\ComposerService;
use Drmovi\PackageGenerator\Utils\FileUtil;

class LaravelPackageDeletion implements Operation
{

    public function __construct(
        private readonly string          $packageComposerName,
        private readonly string          $packageName,
        private readonly string          $packageAbsolutePath,
        private readonly string          $packageNamespace,
        private readonly ComposerFile    $rootComposerFile,
        private readonly ComposerService $composerService,
        private readonly Configs         $configs,
    )
    {
    }

    public function exec(): void
    {
        FileUtil::emptyDirectory($this->configs->getAppPath() . '/bootstrap/cache');
    }

    public function backup(): void
    {
        // TODO: Implement backup() method.
    }

    public function rollback(): void
    {
    }

    public function init(): void
    {
        // TODO: Implement init() method.
    }
}
