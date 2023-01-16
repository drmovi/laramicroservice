<?php

namespace Drmovi\LaraMicroservice\Traits;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
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

    private function restoreComposerFile(string $composerFileContent): void
    {
        File::put(base_path('composer.json'), $composerFileContent);
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
        $name = Str::of($microserviceName)->explode('/');
        return config('laramicroservice.microservice_directory') . '/' . $name[count($name) > 0 ? 1: 0];
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


}
