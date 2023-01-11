<?php

namespace Drmovi\LaraMicroservice\Traits;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

trait Package
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

    private function deletePackageDirectory(string $packageFullDirectory): void
    {
        File::deleteDirectory($packageFullDirectory);
    }

    private function getPackageDirectory(string $packageName): string
    {
        $name = Str::of($packageName)->explode('/');
        return config('laramicroservice.package_directory') . '/' . $name[1];
    }

    private function getPackageName(): string
    {
        $name = $this->ask('What is the composer name of your microservice?');
        if (!preg_match('{^[a-z0-9_.-]+/[a-z0-9_.-]+$}D', $name)) {
            $this->error('Invalid composer name. It should be lowercase and have a vendor name, a forward slash, and a package name, matching: [a-z0-9_.-]+/[a-z0-9_.-]+');
            return $this->getPackageName();
        }
        return $name;
    }

    private function getPackageFullDirectory(string $packageDirectory): string
    {
        return base_path($packageDirectory);
    }


}
