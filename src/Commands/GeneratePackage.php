<?php

namespace Drmovi\PackageGenerator\Commands;

use Composer\Console\Application;
use Drmovi\PackageGenerator\Dtos\Configs;
use Drmovi\PackageGenerator\Actions\PackageGeneration;
use Drmovi\PackageGenerator\Services\ComposerService;
use Drmovi\PackageGenerator\Utils\FileUtil;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'package:new',
    description: 'Creates a new package.',
    hidden: false
)]
class GeneratePackage extends Command
{
    private readonly Configs $configs;
    private readonly ComposerService $composer;


    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $this->composer = new ComposerService($output);
        $this->configs = Configs::loadFromComposer($this->composer->getApplication());
        if (!($this->configs->getVendorName())) {
            $io->error('Vendor name is not set in composer.json, add it to extra.monorepo.vendor_name');
            return Command::FAILURE;
        }
        $packageComposerName = $this->getComposerPackageName($io);
        $operation = new PackageGeneration(
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
        $io->success('Package generated successfully!');
        return Command::SUCCESS;
    }


    private function getComposerPackageName(SymfonyStyle $io): string
    {
        return $io->ask('Name of your package', null, function ($answer) use ($io) {
            $vendorName = $this->configs->getVendorName();
            $regex = '/^[a-z_]+$/';
            if (!preg_match($regex, $answer)) {
                throw new \RuntimeException("Invalid package name. It should match the following regex: $regex");
            }
            if (FileUtil::directoryExist(getcwd() . DIRECTORY_SEPARATOR . $this->configs->getPackagePath() . DIRECTORY_SEPARATOR . $answer)) {
                throw new \RuntimeException("Package with name $answer already exists !!");
            }
            $composerPackageName = $vendorName ? $vendorName . '/' . $answer : $answer;
            $io->info("Package name: $composerPackageName");
            return $composerPackageName;
        });
    }

}
