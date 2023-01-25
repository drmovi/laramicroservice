<?php

namespace Drmovi\LaraMicroservice\Traits;

use Composer\Console\Application;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\DomCrawler\Crawler;
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

    private function getComposerPackageName(): string
    {
        $name = $this->ask('What is the composer name of your microservice?');
        if (!preg_match('{^[a-z0-9_.-]+/[a-z0-9_.-]+$}D', $name)) {
            $this->error('Invalid composer name. It should be lowercase and have a vendor name, a forward slash, and a microservice name, matching: [a-z0-9_.-]+/[a-z0-9_.-]+');
            return $this->getComposerPackageName();
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

    private function runComposerCommand(array $input = []): void
    {
        $application = new Application();
        $application->setAutoExit(false);
        $result = $application->run(new ArgvInput(['composer',...$input]), $this->output);
        if ($result > 0) {
            throw new \Exception('Error while running composer install');
        }
    }

    private function getMicroserviceName(string $composerPackageName): string
    {
        return Str::of($composerPackageName)->explode('/')->pop();
    }

    private function addTestDirectoriesToPhpunitXmlFile(string $microserviceDirectory): void
    {
        $crawler = new Crawler(File::get(base_path('phpunit.xml')));
        $crawler->filterXPath('//phpunit/testsuites/testsuite[@name="Unit"]')->each(function (Crawler $node) use ($crawler, $microserviceDirectory) {
            $child = $crawler->getNode(0)->parentNode->createElement('directory', './' . $microserviceDirectory . '/tests/Unit');
            $child->setAttribute('suffix', 'Test.php');
            $node->getNode(0)->appendChild($child);
        });
        $crawler->filterXPath('//phpunit/testsuites/testsuite[@name="Feature"]')->each(function (Crawler $node) use ($crawler, $microserviceDirectory) {
            $child = $crawler->getNode(0)->parentNode->createElement('directory', './' . $microserviceDirectory . '/tests/Feature');
            $child->setAttribute('suffix', 'Test.php');
            $node->getNode(0)->appendChild($child);
        });
        $this->setPhpunitXmlFileContent($crawler->getNode(0)->ownerDocument->saveXML());
    }
}
