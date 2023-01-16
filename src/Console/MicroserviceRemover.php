<?php

namespace Drmovi\LaraMicroservice\Console;

use Composer\Console\Application;
use Drmovi\LaraMicroservice\Traits\Microservice;
use Illuminate\Console\Command;
use Illuminate\Support\Composer;
use Illuminate\Support\Facades\File;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Process\Process;

class MicroserviceRemover extends Command
{

    use Microservice;

    protected $signature = 'microservice:remove';


    protected $description = 'Remove a microservice skeleton inside a laravel project';


    public function __construct(private readonly Composer $composer)
    {
        parent::__construct();
    }


    public function handle(): void
    {
        [$microserviceName, $microserviceRelativeDirectory] = $this->getMicroserviceData();
        $microserviceRelativeDir = $this->getMicroserviceDirectory($microserviceName);
        $microserviceFullDirectory = base_path($microserviceRelativeDirectory);
        $composerFileContent = File::get(base_path('composer.json'));
        $phpunitXmlFileContent = $this->getPhpunitXmlFileContent();
        $microserviceComposerFileContent = json_decode(File::get($microserviceFullDirectory . '/composer.json'), true);
        $composerPackageName = $microserviceComposerFileContent['name'];


        try {
            $this->removeMicroserviceFromComposer($composerPackageName, $microserviceRelativeDir);
            $this->removeTestDirectoriesToPhpunitXmlFile($microserviceRelativeDirectory);
            $this->deleteMicroserviceDirectory($microserviceFullDirectory);
            $this->composer->dumpAutoloads();
            $this->info('Microservice removed successfully');
        } catch (\Exception $e) {
            $this->error($e->getMessage());
            $this->info('Rolling back...');
            $this->restoreComposerFile($composerFileContent);
            $this->setPhpunitXmlFileContent($phpunitXmlFileContent);
            $this->info('Rolling back completed.');
        }
    }

    private function removeMicroserviceFromComposer(string $composerMicroserviceName, string $microserviceRelativePath): void
    {
        $application = new Application();
        $application->setAutoExit(false);
        $result = $application->run(new ArgvInput(['composer', 'remove', $composerMicroserviceName, '--no-interaction']), $this->output);
        if ($result > 0) {
            throw new \Exception('Error while adding microservice to composer');
        }

        $content = json_decode(File::get(base_path('composer.json')), true);
        $content['repositories'] = collect($content['repositories'] ?? [])->filter(fn($repo) => $repo['url'] !== './' . $microserviceRelativePath)->toArray();
        File::put(base_path('composer.json'), json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    }

    private function removeTestDirectoriesToPhpunitXmlFile(string $microserviceDirectory): void
    {
        $crawler = new Crawler(File::get(base_path('phpunit.xml')));
        $crawler->filterXPath('//phpunit/testsuites/testsuite//*')->each(function (Crawler $node) use ($microserviceDirectory) {
            if (in_array($node->text(), ['./' . $microserviceDirectory . '/tests/Unit', './' . $microserviceDirectory . '/tests/Feature'])) {
                $node->getNode(0)->parentNode->removeChild($node->getNode(0));
            };
        });
        $this->setPhpunitXmlFileContent($crawler->getNode(0)->ownerDocument->saveXML());
    }


    private function getMicroserviceData(): array
    {
        $microserviceName = $this->getMicroserviceMainFolderName();
        $microserviceDirectory = $this->getMicroserviceDirectory($microserviceName);
        $this->info($this->getMicroserviceDirectory($microserviceDirectory));
        if (!File::isDirectory($this->getMicroserviceFullDirectory($microserviceDirectory))) {
            $this->error('Microservice not found');
            return $this->getMicroserviceData();
        }
        return [
            $microserviceName,
            $microserviceDirectory
        ];
    }

    private function getMicroserviceMainFolderName()
    {
        $name = $this->ask('What is the folder name of your microservice?');
        if (!$name) {
            $this->error('Microservice name is required');
            return $this->getMicroserviceMainFolderName();
        }
        return $name;
    }

}
