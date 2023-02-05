<?php

namespace Drmovi\MonorepoGenerator\Commands;

use Composer\Console\Input\InputArgument;
use Drmovi\MonorepoGenerator\Actions\CreatePackageAction;
use Drmovi\MonorepoGenerator\Dtos\ActionDto;
use Drmovi\MonorepoGenerator\Dtos\Configs;
use Drmovi\MonorepoGenerator\Services\ComposerFileService;
use Drmovi\MonorepoGenerator\Services\ComposerService;
use Drmovi\MonorepoGenerator\Utils\FileUtil;
use Drmovi\MonorepoGenerator\Validators\PackageNameValidator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
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
        $this->addArgument('name', InputArgument::OPTIONAL, 'Name of your package', null);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $composerService = new ComposerService($output);
        $configs = Configs::loadFromComposer(new ComposerFileService(getcwd(), $composerService));
        $this->validateNameArg($input, $io, $configs);
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
        return $io->ask('Name of your package', null, function ($answer, $configs) {
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
            FileUtil::directoryExist(getcwd() . DIRECTORY_SEPARATOR . $configs->getPackagePath() . DIRECTORY_SEPARATOR . $name)
            || FileUtil::directoryExist(getcwd() . DIRECTORY_SEPARATOR . $configs->getSharedPackagesPath() . DIRECTORY_SEPARATOR . $name)
        ) {
            throw new \RuntimeException("Package with name $name already exists !!");
        }
    }
}
