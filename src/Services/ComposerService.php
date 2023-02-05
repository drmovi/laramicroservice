<?php

namespace Drmovi\MonorepoGenerator\Services;

use Composer\Console\Application;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\OutputInterface;

class ComposerService
{

    public function __construct(private readonly OutputInterface $output)
    {

    }


    public function runComposerCommand(array $input = []): void
    {
        if ($this->getApplication()->run(new ArgvInput(['composer', ...$input]), $this->output) > 0) {
            throw new \Exception('Error while running composer install');
        }
    }

    public function getApplication(): Application
    {
        $application = new Application();
        $application->setAutoExit(false);
        return $application;
    }
}
