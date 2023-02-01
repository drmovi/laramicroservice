<?php

namespace Drmovi\PackageGenerator\Commands;

use Composer\Console\Input\InputArgument;
use Drmovi\PackageGenerator\Actions\DeletePackageAction;
use Drmovi\PackageGenerator\Dtos\Configs;
use Drmovi\PackageGenerator\Services\ComposerService;
use Drmovi\PackageGenerator\Utils\FileUtil;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;


#[AsCommand(
    name: 'package:delete',
    description: 'Delete a package.',
    hidden: false
)]
class DeletePackageCommand  extends Command
{
    private readonly Configs $configs;
    private readonly ComposerService $composer;


    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::OPTIONAL, 'Name of your package', null);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $this->composer = new ComposerService($output);
        $this->configs = Configs::loadFromComposer($this->composer->getApplication());
        if (!($this->configs->getVendorName())) {
            $io->error('Vendor name is not set in composer.json, add it to extra.monorepo.vendor_name');
            return Command::FAILURE;
        }
        $packageComposerName = $this->getComposerPackageName($io,$input);
        $operation = new DeletePackageAction(
            packageComposerName: $packageComposerName,
            configs: $this->configs,
            composer: $this->composer,
        );
        try {
            $operation->backup();
            $operation->exec();
        } catch (\Throwable $e) {
            $operation->rollback();
            $io->error($e->getMessage());
            return Command::FAILURE;
        }
        $io->success('Package deleted successfully!');
        return Command::SUCCESS;
    }


    private function getComposerPackageName(SymfonyStyle $io,InputInterface $input): string
    {
        $name = $input->getArgument('name');
        if (!is_null($name)) {
            try {
                $this->validatePackageName($name);
                $composerPackageName = $this->configs->getVendorName() . '/' . $name;
                $io->info("Package name: $composerPackageName");
                return $composerPackageName;
            } catch (\Throwable $e) {
                $io->error($e->getMessage());
            }
        }
        return $io->ask('Name of your package', null, function ($answer) use ($io) {
            $this->validatePackageName($answer);
            $composerPackageName = $this->configs->getVendorName() . '/' . $answer;
            $io->info("Package name: $composerPackageName");
            return $composerPackageName;
        });
    }

    private function validatePackageName(string $name): void
    {
        $regex = '/^[a-z_]+$/';
        if (!preg_match($regex, $name)) {
            throw new \RuntimeException("Invalid package name. It should match the following regex: $regex");
        }
        if (!FileUtil::directoryExist(getcwd() . DIRECTORY_SEPARATOR . $this->configs->getPackagePath() . DIRECTORY_SEPARATOR . $name)) {
            throw new \RuntimeException("Package with name $name does not exists !!");
        }
        if($name === 'shared'){
            throw new \RuntimeException("You can't delete the shared package !!");
        }
    }

}
