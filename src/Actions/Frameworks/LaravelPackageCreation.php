<?php

namespace Drmovi\PackageGenerator\Actions\Frameworks;

use Drmovi\PackageGenerator\Contracts\Operation;
use Drmovi\PackageGenerator\Dtos\Configs;
use Drmovi\PackageGenerator\Entities\ComposerFile;
use Drmovi\PackageGenerator\Services\ComposerService;
use Drmovi\PackageGenerator\Utils\FileUtil;
use Symfony\Component\Console\Style\SymfonyStyle;

class LaravelPackageCreation implements Operation
{

    private ComposerFile $appComposerFile;

    public function __construct(
        private readonly string          $packageComposerName,
        private readonly string          $packageName,
        private readonly string          $packageAbsolutePath,
        private readonly string          $packageNamespace,
        private readonly ComposerFile    $rootComposerFile,
        private readonly ComposerService $composerService,
        private readonly SymfonyStyle    $io,
        private readonly Configs         $configs,
    )
    {
        $this->appComposerFile = new ComposerFile(getcwd() . DIRECTORY_SEPARATOR . $this->configs->getAppPath());
    }

    public function init(): void
    {

        $this->installDevDependencies();
        $this->addLaravelScriptsToRoot();
        $this->addAutoloadDev();
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

    private function generateDotEnv(): void
    {
        FileUtil::copyFile($this->configs->getAppPath() . '/.env.example', $this->configs->getAppPath() . '/.env', []);
        exec("php ./{$this->configs->getAppPath()}/artisan key:generate --ansi");
    }

    private function addAutoloadDev(): void
    {
        $appAutoloadDev = $this->appComposerFile->getPsr4Namespace(null, true);
        $data = [];
        foreach ($appAutoloadDev as $namespace => $path) {
            $data[$namespace] = "{$this->configs->getAppPath()}/$path";
        }
        $this->rootComposerFile->addPsr4Namespace($data, true);
    }

    private function installDevDependencies(): void
    {
        try {
            $appDevDependencies = $this->appComposerFile->getRequireDev();
            $rootDevDependencies = $this->rootComposerFile->getRequireDev();
            $commonDevDependencies = array_intersect(array_keys($appDevDependencies), array_keys($rootDevDependencies));
            $requireDevPackages = array_map(fn($package, $version) => "$package:$version", array_keys($appDevDependencies), array_values($appDevDependencies));
            if (!empty($commonDevDependencies)) {
                $this->composerService->runComposerCommand([
                    'remove',
                    '--dev',
                    ...$commonDevDependencies,
                    '--no-interaction'
                ]);
            }
            $this->composerService->runComposerCommand([
                'require',
                '--dev',
                '--with-all-dependencies',
                ...$requireDevPackages,
                '--no-interaction'
            ]);
        } catch (\Throwable $e) {
            $this->io->error($e->getMessage());
            $this->io->warning("Failed to install dev dependencies for your app. Please install them manually.");
        }
    }
}
