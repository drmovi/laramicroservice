<?php

namespace Drmovi\MonorepoGenerator\Commands;

use Composer\Console\Input\InputArgument;
use Drmovi\MonorepoGenerator\Actions\CreatePackageAction;
use Drmovi\MonorepoGenerator\Dtos\ActionDto;
use Drmovi\MonorepoGenerator\Dtos\Configs;
use Drmovi\MonorepoGenerator\Enums\Commands;
use Drmovi\MonorepoGenerator\Services\ComposerFileService;
use Drmovi\MonorepoGenerator\Services\ComposerService;
use Drmovi\MonorepoGenerator\Services\RootComposerFileService;
use Drmovi\MonorepoGenerator\Utils\FileUtil;
use Drmovi\MonorepoGenerator\Validators\PackageNameValidator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'monorepo:package:create',
    description: 'Creates a new package.',
    hidden: false
)]
class MonorepoPackageCreateCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::REQUIRED, 'Name of your package', null)
            ->addArgument('shared', InputArgument::OPTIONAL, 'Add to shared packages', null);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $composerService = new ComposerService($output);
        $configs = Configs::loadFromComposer(new RootComposerFileService(getcwd(), $composerService));
        $this->validateNameArg($input, $io, $configs);
        if (!$configs->isInitialized()) {
            $this->initMonoRepo($io, $output);
            $configs = Configs::loadFromComposer(new RootComposerFileService(getcwd(), $composerService));
        }
        return (new CreatePackageAction(new ActionDto(
            command: $this,
            input: $input,
            output: $output,
            io: $io,
            composerService: $composerService,
            configs: $configs,
        )))->exec();
    }


    private function validateNameArg(InputInterface $input, SymfonyStyle $io, Configs $configs): string
    {
        $name = $input->getArgument('name');
        if (!is_null($name)) {
            try {
                $this->validatePackageName($name, $configs);
                return $name;
            } catch (\Throwable $e) {
                $io->error($e->getMessage());
            }
        }
        return $io->ask('Name of your package', null, function ($answer) use ($configs) {
            $this->validatePackageName($answer, $configs);
            return $answer;
        });
    }

    private function validatePackageName(string $name, Configs $configs): void
    {
        $nameValidator = new PackageNameValidator();
        if (!$nameValidator->validate($name)) {
            throw new \RuntimeException($nameValidator->getErrorMessage());
        }
        if (
            FileUtil::directoryExist(getcwd() . DIRECTORY_SEPARATOR . $configs->getPackagesPath() . DIRECTORY_SEPARATOR . $name)
            || FileUtil::directoryExist(getcwd() . DIRECTORY_SEPARATOR . $configs->getSharedPackagesPath() . DIRECTORY_SEPARATOR . $name)
        ) {
            throw new \RuntimeException("Package with name $name already exists !!");
        }
    }

    private function initMonoRepo(SymfonyStyle $io, OutputInterface $output): void
    {
        $io->warning('Seems like you are trying to create a package in a non-initialized monorepo. Initializing now...');
        $this->getApplication()->find(Commands::MONOREPO_INIT->value)->run(new ArrayInput([]), $output);
    }
}
