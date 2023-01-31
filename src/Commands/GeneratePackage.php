<?php

namespace Drmovi\PackageGenerator\Commands;

use Composer\Console\Application;
use Drmovi\PackageGenerator\Dtos\Configs;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(
    name: 'package:new',
    description: 'Creates a new package.',
    hidden: false
)]
class GeneratePackage extends Command
{
    private readonly Configs $configs;
    private readonly Application $composer;

    public function __construct(string $name = null)
    {
        parent::__construct($name);
        $this->composer = new Application();
        $this->configs = Configs::loadFromComposer($this->composer);
    }


    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $helper = $this->getHelper('question');
        $packageComposerName = $this->getPackageComposerName($helper, $input, $output);
        $output->writeln("<info>Package name: $packageComposerName</info>");

        return Command::SUCCESS;
    }

    private function getPackageComposerName(mixed $helper, InputInterface $input, OutputInterface $output): string
    {
        $namePrefix = $this->configs->getNamePrefix();
        $regex = $namePrefix ? '/^[a-z_]+$/' :'/^[a-z]+\/[a-z0-9_]+$/';
        $answer = $helper->ask($input, $output, new Question('Name of your package:', 'localhost'));
        if (!preg_match($regex, $answer)) {
            $output->writeln("<error>Invalid package name. It should match the following regex: $regex</error>");
            return $this->getPackageComposerName($helper, $input, $output);
        }
        return $namePrefix ? $namePrefix . '/' . $answer : $answer;
    }
}
