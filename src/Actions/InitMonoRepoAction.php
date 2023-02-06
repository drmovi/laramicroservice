<?php

namespace Drmovi\MonorepoGenerator\Actions;

use Drmovi\MonorepoGenerator\Contracts\Operation;
use Drmovi\MonorepoGenerator\Dtos\ActionDto;
use Drmovi\MonorepoGenerator\Enums\Commands;
use Drmovi\MonorepoGenerator\Enums\Modes;
use Drmovi\MonorepoGenerator\Factories\FrameworkOperationFactory;
use Drmovi\MonorepoGenerator\Services\PhpstanNeonService;
use Drmovi\MonorepoGenerator\Services\RootComposerFileService;
use Drmovi\MonorepoGenerator\Utils\FileUtil;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;

class InitMonoRepoAction implements Operation
{


    private RootComposerFileService $rootComposerFileService;

    public function __construct(protected readonly ActionDto $actionDto)
    {
        $this->rootComposerFileService = new RootComposerFileService(getcwd(), $this->actionDto->composerService);
    }


    public function exec(): int
    {
        $frameworkPackageOperation = (new FrameworkOperationFactory())->make($this->actionDto);
        $this->backup();
        $frameworkPackageOperation->backup();
        try {
            $this->_exec();
            $frameworkPackageOperation->exec();
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $frameworkPackageOperation->rollback();
            $this->rollback();
            $this->actionDto->io->error($e->getMessage());
            return Command::FAILURE;
        }
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
            'app_path' => $this->actionDto->configs->getAppPath(),
            'packages_path' => $this->actionDto->configs->getPackagesPath(),
            'shared_packages_path' => $this->actionDto->configs->getSharedPackagesPath(),
            'vendor_name' => $this->actionDto->configs->getVendorName(),
            'framework' => $this->actionDto->configs->getFramework(),
            'mode' => $this->actionDto->configs->getMode(),
            'dev_conf_path' => $this->actionDto->configs->getDevConfPath(),
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
        $this->copyStubDirectory(
            source: 'devops/root',
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
        $this->removePackagesFolders();
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
        FileUtil::removeDirectory(getcwd() . DIRECTORY_SEPARATOR . $this->actionDto->configs->getDevConfPath());
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
        $this->copyStubFile(
            source: 'devconf/phpunit.xml',
            destination: getcwd() . '/phpunit.xml',
        );
    }


    private function installPackageBoundariesPlugin(): void
    {
        if ($this->actionDto->configs->getMode() !== Modes::MICROSERVICE->value) {
            return;
        }
        $this->rootComposerFileService->runComposerCommand([
            'require',
            '--no-plugins',
            '--no-interaction',
            '--dev',
            '--with-all-dependencies',
            'drmovi/phpstan-package-boundaries-plugin',
        ]);

        (new PhpstanNeonService(getcwd() . DIRECTORY_SEPARATOR . $this->actionDto->configs->getDevConfPath()))->addRules([
            'Drmovi\PackageBoundaries\PackageBoundaries'
        ]);

    }


    private function copyGitIgnoreFileToRoot(): void
    {
        $this->copyStubFile(
            source: 'devconf/.gitignore',
            destination: getcwd() . '/.gitignore',
        );
    }

    private function installPhpstan(): void
    {

        $this->copyStubFile(
            source: 'devconf/conf/phpstan.neon',
            destination: getcwd() . DIRECTORY_SEPARATOR . $this->actionDto->configs->getDevConfPath() . '/phpstan.neon',
        );
        FileUtil::makeFile(getcwd() . DIRECTORY_SEPARATOR . $this->actionDto->configs->getDevConfPath() . '/phpstan-baseline.neon', '');


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

    }

    private function installPsalm(): void
    {
        $this->copyStubFile(
            source: 'devconf/conf/psalm.xml',
            destination: getcwd() . DIRECTORY_SEPARATOR . $this->actionDto->configs->getDevConfPath() . '/psalm.xml',
        );
        $this->rootComposerFileService->runComposerCommand([
            'require',
            '--dev',
            '--no-interaction',
            'vimeo/psalm',
        ]);
    }


    private function copyStubFile(string $source, string $destination)
    {
        FileUtil::copyFile(
            sourceFile: __DIR__ . '/../../stubs/' . $source,
            destinationFile: $destination,
            replacements: [
                '{{APP_PATH}}' => $this->actionDto->configs->getAppPath(),
                '{{PACKAGES_PATH}}' => $this->actionDto->configs->getPackagesPath(),
            ]);
    }

    private function copyStubDirectory(string $source, string $destination)
    {
        FileUtil::copyDirectory(
            source: __DIR__ . '/../../stubs/' . $source,
            destination: $destination,
            replacements: [
                '{{APP_PATH}}' => $this->actionDto->configs->getAppPath(),
                '{{PACKAGES_PATH}}' => $this->actionDto->configs->getPackagesPath(),
            ]);
    }

    private function removePackagesFolders(): void
    {
        FileUtil::removeDirectory(getcwd() . DIRECTORY_SEPARATOR . $this->actionDto->configs->getPackagesPath());
        FileUtil::removeDirectory(getcwd() . DIRECTORY_SEPARATOR . $this->actionDto->configs->getSharedPackagesPath());
    }


}
