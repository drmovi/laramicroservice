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
        $this->addAppToRootComposerFile();
        $this->generateDotEnv();
        $this->installOctane();
        $this->installDevDependencies();
        $this->installLintersAndFixers();
        $this->updateMakeFile();
        return Command::SUCCESS;
    }

    public function backup(): void
    {
    }

    public function rollback(): void
    {
        FileUtil::removeFile(getcwd() . DIRECTORY_SEPARATOR . $this->actionDto->configs->getAppPath() . DIRECTORY_SEPARATOR . 'vendor');
        $this->appComposerService->rollback();
        $this->rootComposerService->rollback();
        FileUtil::removeDirectory(getcwd() . DIRECTORY_SEPARATOR . $this->actionDto->configs->getAppPath());
    }


    private function generateDotEnv(): void
    {
        FileUtil::copyFile($this->actionDto->configs->getAppPath() . '/.env.example', $this->actionDto->configs->getAppPath() . '/.env', []);
        exec("php ./{$this->actionDto->configs->getAppPath()}/artisan key:generate --ansi=true");
    }


    private function addAppToRootComposerFile(): void
    {
        $name = "{$this->actionDto->configs->getVendorName()}/{$this->actionDto->configs->getAppPath()}";
        $this->appComposerService->setName($name);
        $this->appComposerService->setVersion('1.0');
        $this->rootComposerService->addRepository($this->actionDto->configs->getFramework(), './' . $this->actionDto->configs->getAppPath());
        $appPsr4DevNamespaces = $this->appComposerService->getPsr4Namespace(null, true);
        $devNamespaces = [];
        foreach ($appPsr4DevNamespaces as $namespace => $path) {
            $devNamespaces[$namespace] = $this->actionDto->configs->getAppPath() . '/' . $path;
        }
        $this->rootComposerService->addPsr4Namespace($devNamespaces, true);
        $this->rootComposerService->runComposerCommand(['require', $name, '--with-all-dependencies', '--no-interaction']);
    }


    private function addLaravelScriptsToRootComposerFile(): void
    {
        $this->rootComposerService->addScripts([
            'post-autoload-dump' => [
                "rm -rf ./app/vendor && ln -s ./../vendor ./app",
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

    private function installLintersAndFixers(): void
    {
        $this->rootComposerService->runComposerCommand([
            'require',
            '--dev',
            '--with-all-dependencies',
            '--no-interaction',
            'nunomaduro/larastan',
            'psalm/plugin-laravel'
        ]);
        exec("./vendor/bin/psalm-plugin enable -c ./{$this->actionDto->configs->getDevConfPath()}/psalm.xml psalm/plugin-laravel");
        $phpstanNeonFileService = new PhpstanNeonService($this->actionDto->configs->getDevConfPath());
        $phpstanNeonFileService->addExcludePaths(["../{$this->actionDto->configs->getAppPath()}/bootstrap/cache", "../{$this->actionDto->configs->getAppPath()}/storage"]);
    }

    private function updateMakeFile()
    {
        FileUtil::copyFile(
            sourceFile: getcwd() . '/makefile',
            destinationFile: getcwd() . '/makefile',
            replacements: [
                '{{FRAMEWORK_STYLE_FIX_COMMAND}}' => './vendor/bin/pint',
                '{{FRAMEWORK_STYLE_CHECK_COMMAND}}' => './vendor/bin/pint --test',
                '{{FRAMEWORK_MAKEFILE_COMMANDS}}' => <<<EOT
artisan:
	@php ./app/artisan $(filter-out $@,$(MAKECMDGOALS))
EOT
            ]);
    }

    private function installOctane(): void
    {
        $this->rootComposerService->runComposerCommand([
            'require',
            '--with-all-dependencies',
            '--no-interaction',
            'laravel/octane'
        ]);
        $this->appComposerService->addRequires(['laravel/octane' => $this->rootComposerService->getRequireValue('laravel/octane')]);
        $this->rootComposerService->removeRequires(['laravel/octane']);
        $this->rootComposerService->runComposerCommand(['update', '--no-interaction', '--with-all-dependencies']);
        exec("php ./{$this->actionDto->configs->getAppPath()}/artisan octane:install --server=swoole");
    }
}
