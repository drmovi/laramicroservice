<?php

namespace Drmovi\PackageGenerator\Actions\Frameworks;

use Drmovi\PackageGenerator\Contracts\Operation;
use Drmovi\PackageGenerator\Dtos\Configs;
use Drmovi\PackageGenerator\Entities\ComposerFile;
use Drmovi\PackageGenerator\Utils\FileUtil;

class LaravelPackageCreation implements Operation
{

    public function __construct(
        private readonly string       $packageComposerName,
        private readonly string       $packageName,
        private readonly string       $packageAbsolutePath,
        private readonly string       $packageNamespace,
        private readonly ComposerFile $rootComposerFile,
        private readonly Configs      $configs,
    )
    {
    }

    public function init(): void
    {
        $this->addLaravelScriptsToRoot();
        $this->generateDotEnv();
    }

    public function exec(): void
    {

    }

    public function backup(): void
    {
        // TODO: Implement backup() method.
    }

    public function rollback(): void
    {
    }

    private function addLaravelScriptsToRoot(): void
    {
        $this->rootComposerFile->addScripts([
            'post-autoload-dump' => [
                "Illuminate\\Foundation\\ComposerScripts::postAutoloadDump",
                "@php {$this->configs->getAppPath()}/artisan package:discover --ansi"
            ],
            'post-update-cmd' => [
                "@php {$this->configs->getAppPath()}/artisan vendor:publish --tag=laravel-assets --ansi --force"
            ],
        ]);
    }

    private function generateDotEnv()
    {
        FileUtil::copyFile($this->configs->getAppPath() . '/.env.example', $this->configs->getAppPath() . '/.env',[]);
        exec("php ./{$this->configs->getAppPath()}/artisan key:generate --ansi");
    }
}
