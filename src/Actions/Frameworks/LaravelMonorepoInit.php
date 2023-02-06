<?php

namespace Drmovi\MonorepoGenerator\Actions\Frameworks;

use Drmovi\MonorepoGenerator\Contracts\Operation;
use Drmovi\MonorepoGenerator\Dtos\ActionDto;
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

    public function __construct(protected readonly ActionDto $actionDto)
    {
        $this->rootComposerService = new RootComposerFileService(getcwd(), $this->actionDto->composerService);
        $this->appComposerService = new ComposerFileService($this->actionDto->configs->getAppPath(), $this->actionDto->composerService);

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
        FileUtil::removeDirectory($this->actionDto->configs->getAppPath());
    }


    private function generateDotEnv(): void
    {
        FileUtil::copyFile($this->actionDto->configs->getAppPath() . '/.env.example', $this->actionDto->configs->getAppPath() . '/.env', []);
        $this->getLaravelAppService()->artisan('key:generate', ['--ansi' => true]);
    }


    private function addAppRepositoryToRootComposerFile(): void
    {
        $this->rootComposerService->addRepository($this->actionDto->configs->getFramework(), './' . $this->actionDto->configs->getAppPath());
    }


    private function addLaravelScriptsToRootComposerFile(): void
    {
        $this->rootComposerService->addScripts([
            'post-autoload-dump' => [
                "Illuminate\\Foundation\\ComposerScripts::postAutoloadDump",
                "@php {$this->actionDto->configs->getAppPath()}/artisan package:discover --ansi"
            ],
            'post-update-cmd' => [
                "@php {$this->actionDto->configs->getAppPath()}/artisan vendor:publish --tag=laravel-assets --ansi --force"
            ],
        ]);
    }


    private function installLaravelProject(): void
    {
        $this->actionDto->composerService->runComposerCommand([
            'create-project',
            'laravel/laravel',
            '--no-install',
            '--no-scripts',
            '--no-interaction',
            $this->actionDto->configs->getAppPath(),
        ]);
    }

    private function symLinkRootVendorToApp(): void
    {
        FileUtil::createSymLink('./../vendor', './' . $this->actionDto->configs->getAppPath() . '/vendor');
    }

    private function getLaravelAppService(): LaravelAppService
    {
        return LaravelAppService::instance($this->actionDto);
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
            $this->actionDto->io->error($e->getMessage());
            $this->actionDto->io->warning("Failed to install dev dependencies for your app. Please install them manually.");
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
        exec("./vendor/bin/psalm-plugin enable -c {$this->actionDto->configs->getDevConfPath()}/psalm.xml psalm/plugin-laravel");
        $phpstanNeonFileService = new PhpstanNeonService($this->actionDto->configs->getDevConfPath());
        $phpstanNeonFileService->addExtensionRefs(['./vendor/nunomaduro/larastan/extension.neon']);
        $phpstanNeonFileService->addExcludePaths(["../{$this->actionDto->configs->getAppPath()}/bootstrap/cache", "../{$this->actionDto->configs->getAppPath()}/storage"]);
        exec("./vendor/bin/psalm --config=./{$this->actionDto->configs->getDevConfPath()}/psalm.xml --set-baseline=psalm-baseline.xml --no-cache");
        exec("./vendor/bin/phpstan analyse --memory-limit=2G --configuration={$this->actionDto->configs->getDevConfPath()}/phpstan.neon --allow-empty-baseline --generate-baseline={$this->actionDto->configs->getDevConfPath()}/phpstan-baseline.neon");
    }
}
