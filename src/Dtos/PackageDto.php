<?php

namespace Drmovi\MonorepoGenerator\Dtos;

use Drmovi\MonorepoGenerator\Services\ComposerService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class PackageDto
{


    private function __construct(
        public readonly Command         $command,
        public readonly InputInterface  $input,
        public readonly OutputInterface $output,
        public readonly SymfonyStyle    $io,
        public readonly ComposerService $composerService,
        public readonly Configs         $configs,
        public readonly PackageData     $packageData,
    )
    {
    }


    public static function loadFromActionDto(ActionDto $actionDto,PackageData $packageData):self{
        return new self(
            command: $actionDto->command,
            input: $actionDto->input,
            output: $actionDto->output,
            io: $actionDto->io,
            composerService: $actionDto->composerService,
            configs: $actionDto->configs,
            packageData: $packageData
        );
    }

}
