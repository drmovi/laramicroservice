<?php

namespace Drmovi\LaraMicroservice\Traits;

use Composer\Console\Application;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Process\Process;

trait Microservice
{

    private function getPhpunitXmlFileContent(): string
    {
        return File::get(base_path('phpunit.xml'));
    }

    private function setPhpunitXmlFileContent(string $content): void
    {
        File::put(base_path('phpunit.xml'), $content);
    }

    private function restoreComposerFile(string $composerFileContent, string $composerLockFileContent): void
    {
        File::put(base_path('composer.json'), $composerFileContent);
        File::put(base_path('composer.lock'), $composerLockFileContent);
        $command = array_merge($this->composer->findComposer(), ['install']);

        $process = (new Process($command, base_path()))->setTimeout(null);

        $process->run(function (string $type, string $data) {
            $this->info($data);
        });
    }

    private function deleteMicroserviceDirectory(string $microserviceFullDirectory): void
    {
        File::deleteDirectory($microserviceFullDirectory);
    }

    private function getMicroserviceDirectory(string $microserviceName): string
    {
        $name = explode('/', $microserviceName);
        return config('laramicroservice.microservice_directory') . '/' . $name[count($name) > 1 ? 1 : 0];
    }

    private function getMicroserviceName(): string
    {
        $name = $this->ask('What is the composer name of your microservice?');
        if (!preg_match('{^[a-z0-9_.-]+/[a-z0-9_.-]+$}D', $name)) {
            $this->error('Invalid composer name. It should be lowercase and have a vendor name, a forward slash, and a microservice name, matching: [a-z0-9_.-]+/[a-z0-9_.-]+');
            return $this->getMicroserviceName();
        }
        return $name;
    }

    private function getMicroserviceFullDirectory(string $microserviceDirectory): string
    {
        return base_path($microserviceDirectory);
    }

    private function getSharedPackageDirectory(): string
    {
        return config('laramicroservice.microservice_directory') . '/' . config('laramicroservice.microservice_shared_package_name');
    }

    private function getMicroserviceClassName(string $microserviceName): string
    {
        return Str::studly(Str::of($microserviceName)->explode('/')->pop());
    }

    private function composerUpdate(): void
    {
        $application = new Application();
        $application->setAutoExit(false);
        $result = $application->run(new ArgvInput(['composer', 'update', '--no-interaction']), $this->output);
        if ($result > 0) {
            throw new \Exception('Error while running composer install');
        }
    }
}
