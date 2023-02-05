<?php

namespace Drmovi\MonorepoGenerator\Actions\Frameworks;

use Drmovi\MonorepoGenerator\Contracts\Operation;
use Drmovi\MonorepoGenerator\Dtos\PackageDto;
use Drmovi\MonorepoGenerator\Services\ComposerFileService;
use Drmovi\MonorepoGenerator\Services\LaravelAppService;
use Drmovi\MonorepoGenerator\Services\PhpstanNeonService;
use Drmovi\MonorepoGenerator\Services\RootComposerFileService;
use Drmovi\MonorepoGenerator\Utils\FileUtil;
use Symfony\Component\Console\Command\Command;

class LaravelMonorepoInit implements Operation
{
    private RootComposerFileService $rootComposerService;
    private ComposerFileService $appComposerService;

    public function __construct(protected readonly PackageDto $packageDto)
    {
        $this->rootComposerService = new RootComposerFileService(getcwd(), $this->packageDto->composerService);
        $this->appComposerService = new ComposerFileService($this->packageDto->configs->getAppPath(), $this->packageDto->composerService);

    }

    public function exec(): int
    {
        $this->installLaravelProject();
        $this->symLinkRootVendorToApp();
        $this->addLaravelScriptsToRootComposerFile();
        $this->addAppRepositoryToRootComposerFile();
        $this->generateDotEnv();
        $this->installDevDependencies();
        $this->installLintersAndFixers();
        return Command::SUCCESS;
    }

    public function backup(): void
    {
    }

    public function rollback(): void
    {
        $this->appComposerService?->rollback();
        $this->rootComposerService->rollback();
        FileUtil::removeDirectory($this->packageDto->configs->getAppPath());
    }


    private function generateDotEnv(): void
    {
        FileUtil::copyFile($this->packageDto->configs->getAppPath() . '/.env.example', $this->packageDto->configs->getAppPath() . '/.env', []);
        $this->getLaravelAppService()->artisan('key:generate', ['--ansi' => true]);
    }


    private function addAppRepositoryToRootComposerFile(): void
    {
        $this->rootComposerService->addRepository($this->packageDto->configs->getFramework(), './' . $this->packageDto->configs->getAppPath());
    }


    private function addLaravelScriptsToRootComposerFile(): void
    {
        $this->rootComposerService->addScripts([
            'post-autoload-dump' => [
                "Illuminate\\Foundation\\ComposerScripts::postAutoloadDump",
                "@php {$this->packageDto->configs->getAppPath()}/artisan package:discover --ansi"
            ],
            'post-update-cmd' => [
                "@php {$this->packageDto->configs->getAppPath()}/artisan vendor:publish --tag=laravel-assets --ansi --force"
            ],
        ]);
    }


    private function installLaravelProject(): void
    {
        $this->packageDto->composerService->runComposerCommand([
            'create-project',
            'laravel/laravel',
            '--no-install',
            '--no-scripts',
            '--no-interaction',
            $this->packageDto->configs->getAppPath(),
        ]);
    }

    private function symLinkRootVendorToApp(): void
    {
        FileUtil::createSymLink('./' . $this->packageDto->configs->getAppPath(), './../vendor');
    }

    private function getLaravelAppService(): LaravelAppService
    {
        return LaravelAppService::instance($this->packageDto);
    }

    private function installDevDependencies(): void
    {
        try {
            $appDevDependencies = $this->appComposerService->getRequireDev();
            $rootDevDependencies = $this->rootComposerService->getRequireDev();
            $commonDevDependencies = array_intersect(array_keys($appDevDependencies), array_keys($rootDevDependencies));
            $requireDevPackages = array_map(fn($package, $version) => "$package:$version", array_keys($appDevDependencies), array_values($appDevDependencies));
            if (!empty($commonDevDependencies)) {
                $this->rootComposerService->runComposerCommand([
                    'remove',
                    '--dev',
                    ...$commonDevDependencies,
                    '--no-interaction'
                ]);
            }
            $this->rootComposerService->runComposerCommand([
                'require',
                '--dev',
                '--with-all-dependencies',
                ...$requireDevPackages,
                '--no-interaction'
            ]);
        } catch (\Throwable $e) {
            $this->packageDto->io->error($e->getMessage());
            $this->packageDto->io->warning("Failed to install dev dependencies for your app. Please install them manually.");
        }
    }

    private function installLintersAndFixers()
    {
        $this->rootComposerService->runComposerCommand([
            'require',
            '--dev',
            '--with-all-dependencies',
            '--no-interaction',
            'nunomaduro/larastan',
            'psalm/plugin-laravel'
        ]);
        exec("./vendor/bin/psalm-plugin enable -c {$this->packageDto->configs->getConfPath()}/psalm.xml psalm/plugin-laravel");
        $phpstanNeonFileService = new PhpstanNeonService($this->packageDto->configs->getConfPath());
        $phpstanNeonFileService->addExtensionRefs( ['./vendor/nunomaduro/larastan/extension.neon']);
        $phpstanNeonFileService->addExcludePaths(["../{$this->packageDto->configs->getAppPath()}/bootstrap/cache", "../{$this->packageDto->configs->getAppPath()}/storage"]);
    }
}
