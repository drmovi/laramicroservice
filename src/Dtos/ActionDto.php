<?php

namespace Drmovi\MonorepoGenerator\Dtos;

use Drmovi\MonorepoGenerator\Services\ComposerService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ActionDto
{

    public function __construct(
        public readonly Command     $command,
        public readonly InputInterface  $input,
        public readonly OutputInterface $output,
        public readonly SymfonyStyle    $io,
        public readonly ComposerService $composerService,
        public readonly Configs         $configs
    )
    {
    }
}
