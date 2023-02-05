<?php

namespace Drmovi\MonorepoGenerator\Actions;

use Drmovi\MonorepoGenerator\Dtos\ActionDto;
use Drmovi\MonorepoGenerator\Enums\Commands;
use Drmovi\MonorepoGenerator\Enums\Modes;
use Drmovi\MonorepoGenerator\Services\PhpstanNeonService;
use Drmovi\MonorepoGenerator\Services\RootComposerFileService;
use Drmovi\MonorepoGenerator\Traits\CopyStubFiles;
use Drmovi\MonorepoGenerator\Utils\FileUtil;
use Symfony\Component\Console\Input\ArrayInput;

class InitMonoRepoAction extends PackageAction
{

    use CopyStubFiles;

    private RootComposerFileService $rootComposerFileService;

    public function __construct(protected readonly ActionDto $actionDto)
    {
        parent::__construct($actionDto);
        $this->rootComposerFileService = new RootComposerFileService(getcwd(), $this->actionDto->composerService);
    }

    protected function _exec(): void
    {
        $this->setMonoRepoConfigs();
        $this->copyGitIgnoreFileToRoot();
        $this->createRootK8sFiles();
        $this->installPhpunit();
        $this->installPhpstan();
        $this->installPsalm();
        $this->installPackageBoundariesPlugin();
        $this->installSharedPackages();
    }


    private function setMonoRepoConfigs(): void
    {
        $this->rootComposerFileService->addMonoRepoConfigs([
            'app_path' => $this->actionDto->input->getArgument('app_path'),
            'packages_path' => $this->actionDto->input->getArgument('packages_path'),
            'shared_packages_path' => $this->actionDto->input->getArgument('shared_packages_path'),
            'vendor_name' => $this->actionDto->input->getArgument('vendor_name'),
            'framework' => $this->actionDto->input->getArgument('framework'),
            'mode' => $this->actionDto->input->getArgument('mode'),
        ]);
    }

    private function installSharedPackages(): void
    {
        $packages = explode(',', $this->actionDto->input->getArgument('shared_packages'));
        foreach ($packages as $package) {
            $this
                ->actionDto
                ->command
                ->getApplication()
                ->find(Commands::MONOREPO_PACKAGE_CREATE->value)
                ->run(new ArrayInput(['name' => $package, 'shared' => true]), $this->actionDto->output);
        }
    }

    private function createRootK8sFiles(): void
    {
        if ($this->actionDto->configs->getMode() !== Modes::MICROSERVICE->value) {
            return;
        }
        FileUtil::copyDirectory(
            source: __DIR__ . '/../../stubs/devops/root',
            destination: getcwd()
        );
    }

    public function backup(): void
    {
        $this->rootComposerFileService->backup();
    }

    public function rollback(): void
    {
        $this->rootComposerFileService->rollback();
        $this->removeK8sFiles();
        $this->removeDevconfFiles();
    }

    private function removeK8sFiles(): void
    {
        FileUtil::removeDirectory(getcwd() . DIRECTORY_SEPARATOR . 'k8s');
        FileUtil::removeFile(getcwd() . DIRECTORY_SEPARATOR . 'Dockerfile');
        FileUtil::removeFile(getcwd() . DIRECTORY_SEPARATOR . 'skaffold.yaml');
        FileUtil::removeFile(getcwd() . DIRECTORY_SEPARATOR . '.dockerignore');
    }

    private function removeDevconfFiles(): void
    {
        FileUtil::removeDirectory(getcwd() . DIRECTORY_SEPARATOR . 'conf');
        FileUtil::removeFile(getcwd() . DIRECTORY_SEPARATOR . 'phpunit.xml');
    }

    private function installPhpunit(): void
    {
        $this->rootComposerFileService->runComposerCommand([
            'require',
            '--dev',
            '--with-all-dependencies',
            '--no-interaction',
            'phpunit/phpunit'
        ]);
        $this->copyStubFiles(
            source: 'devconf/phpunit.xml',
            destination: getcwd() . '/phpunit.xml',
            composerName: $this->packageData->packageComposerName,
            packageNamespace: $this->packageData->packageNamespace,
            packageName: $this->packageData->packageName,
            appPath: $this->actionDto->configs->getAppPath(),
            packagePath: $this->actionDto->configs->getPackagePath()
        );
    }


    private function installPackageBoundariesPlugin(): void
    {
        if ($this->actionDto->configs->getMode() !== Modes::MICROSERVICE->value) {
            return;
        }
        $this->rootComposerFileService->runComposerCommand([
            'config',
            '--no-plugins',
            '--no-interaction',
            'drmovi/phpstan-package-boundaries-plugin',
            true,
        ]);
        $this->rootComposerFileService->runComposerCommand([
            'config',
            '--no-plugins',
            '--no-interaction',
            'drmovi/phpstan-package-boundaries-plugin',
            true,
        ]);

        (new PhpstanNeonService(getcwd() . '/conf'))->addRules([
            'Drmovi\PackageBoundaries\PackageBoundaries'
        ]);

    }


    private function copyGitIgnoreFileToRoot(): void
    {
        FileUtil::copyFile(
            sourceFile: __DIR__ . '/../../stubs/devconf/.gitignore',
            destinationFile: getcwd() . '/.gitignore',
            replacements: []
        );
    }

    private function installPhpstan(): void
    {

        $this->copyStubFiles(
            source: 'devconf/conf/phpstan.neon',
            destination: getcwd() . '/conf/phpstan.neon',
            composerName: $this->packageData->packageComposerName,
            packageNamespace: $this->packageData->packageNamespace,
            packageName: $this->packageData->packageName,
            appPath: $this->actionDto->configs->getAppPath(),
            packagePath: $this->actionDto->configs->getPackagePath()
        );
        FileUtil::makeFile(getcwd() . DIRECTORY_SEPARATOR . $this->actionDto->configs->getConfPath() . '/phpstan-baseline.neon', '');


        $this->rootComposerFileService->runComposerCommand([
            'config',
            '--no-plugins',
            '--no-interaction',
            'allow-plugins.phpstan/extension-installer',
            true,
        ]);

        $this->rootComposerFileService->runComposerCommand([
            'require',
            '--dev',
            '--no-interaction',
            'phpstan/phpstan',
            'phpstan/extension-installer',
        ]);

        exec("./vendor/bin/phpstan analyse --memory-limit=2G --configuration={$this->actionDto->configs->getConfPath()}/phpstan.neon --allow-empty-baseline --generate-baseline={$this->actionDto->configs->getConfPath()}/phpstan-baseline.neon");
    }

    private function installPsalm(): void
    {
        $this->copyStubFiles(
            source: 'devconf/conf/psalm.xml',
            destination: getcwd() . '/conf/psalm.xml',
            composerName: $this->packageData->packageComposerName,
            packageNamespace: $this->packageData->packageNamespace,
            packageName: $this->packageData->packageName,
            appPath: $this->actionDto->configs->getAppPath(),
            packagePath: $this->actionDto->configs->getPackagePath()
        );
        $this->rootComposerFileService->runComposerCommand([
            'require',
            '--dev',
            '--no-interaction',
            'vimeo/psalm',
        ]);
        exec("./vendor/bin/psalm --config=./{$this->actionDto->configs->getConfPath()}/psalm.xml --set-baseline=psalm-baseline.xml --no-cache");
    }


}
