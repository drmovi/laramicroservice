<?php

namespace Drmovi\MonorepoGenerator\Commands;

use Composer\Console\Input\InputArgument;
use Drmovi\MonorepoGenerator\Actions\InitMonoRepoAction;
use Drmovi\MonorepoGenerator\Dtos\ActionDto;
use Drmovi\MonorepoGenerator\Dtos\Configs;
use Drmovi\MonorepoGenerator\Enums\ConstData;
use Drmovi\MonorepoGenerator\Enums\Frameworks;
use Drmovi\MonorepoGenerator\Enums\Modes;
use Drmovi\MonorepoGenerator\Services\ComposerService;
use Drmovi\MonorepoGenerator\Services\RootComposerFileService;
use Drmovi\MonorepoGenerator\Validators\PathValidator;
use Drmovi\MonorepoGenerator\Validators\VendorNameValidator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'monorepo:init',
    description: 'Initialize a monorepo project.',
    hidden: false
)]
class MonorepoInitCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('vendor_name', InputArgument::OPTIONAL, 'Vendor name used as namespace prefix', null)
            ->addArgument('framework', InputArgument::OPTIONAL, 'Framework app to install e.g. laravel', null)
            ->addArgument('mode', InputArgument::OPTIONAL, 'Monorepo mode e.g microservice', null)
            ->addArgument('app_path', InputArgument::OPTIONAL, 'Path to which framework app will be installed', null)
            ->addArgument('conf_path', InputArgument::OPTIONAL, 'Path which development configuration files will be added e.g. phpstan.neon', null)
            ->addArgument('packages_path', InputArgument::OPTIONAL, 'Path that will contains all packages or services', null)
            ->addArgument('shared_packages_path', InputArgument::OPTIONAL, 'Path that will contains all shared packages', null)
            ->addArgument('shared_packages', InputArgument::OPTIONAL, 'Comma separated list of shared packages stubs to be added', null);
    }


    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $composerService = new ComposerService($output);
        $rootComposerFileService = new RootComposerFileService(getcwd(), $composerService);
        if ($rootComposerFileService->getMonorepoData()) {
            $io->error('Monorepo is already initialized.');
            return Command::FAILURE;
        }

        $input->setArgument('vendor_name', $io->ask('What\'s your desired vendor name', 'drmovi', function ($answer) {
            $validator = new VendorNameValidator();
            if (!$validator->validate($answer)) {
                throw new \RuntimeException($validator->getErrorMessage());
            }
        }));
        $input->setArgument('framework', $io->choice('What\'s your framework?', Frameworks::valuesToArray(), Frameworks::LARAVEL->value));
        $input->setArgument('mode', $io->choice('What\'s your project type', Modes::valuesToArray(), Modes::MICROSERVICE->value));
        $input->setArgument('app_path', $io->ask('What\'s your desired app path', 'app', function ($answer) {
            $validator = new PathValidator();
            if (!$validator->validate($answer)) {
                throw new \RuntimeException($validator->getErrorMessage());
            }
        }));
        $input->setArgument('conf_path', $io->ask('What\'s your desired development configuration path "will contain phpstan and psalm files"', 'devconf', function ($answer) {
            $validator = new PathValidator();
            if (!$validator->validate($answer)) {
                throw new \RuntimeException($validator->getErrorMessage());
            }
        }));
        $input->setArgument('packages_path', $io->ask('What\'s your desired packages path', 'packages', function ($answer) {
            $validator = new PathValidator();
            if (!$validator->validate($answer)) {
                throw new \RuntimeException($validator->getErrorMessage());
            }
        }));

        $input->setArgument('shared_packages_path', $io->ask('What\'s your desired packages path', 'shared', function ($answer) use ($input) {
            $validator = new PathValidator();
            if (!$validator->validate($answer)) {
                throw new \RuntimeException($validator->getErrorMessage());
            }
            if ($answer === $input->getArgument('packages_path')) {
                throw new \RuntimeException('Shared packages path cannot be the same as packages path');
            }
        }));
        $io->note('a shared package named ' . ConstData::API_PACKAGE_NAME->value . ' will be added to shard packages path and will contains all shared code from all packages');
        $input->setArgument('shared_packages', ConstData::API_PACKAGE_NAME->value);

        return (new InitMonoRepoAction(new ActionDto(
            command: $this,
            input: $input,
            output: $output,
            io: $io,
            composerService: $composerService,
            configs: Configs::loadFromInput($input),
        )))->exec();
    }
}
