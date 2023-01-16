<?php

namespace Drmovi\LaraMicroservice\Console;

use Drmovi\LaraMicroservice\Traits\Microservice;
use Illuminate\Console\Command;
use Illuminate\Support\Composer;
use Illuminate\Support\Facades\File;
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
        [$microserviceName, $microserviceDirectory] = $this->getMicroserviceData();
        $microserviceFullDirectory = base_path($microserviceDirectory);
        $composerFileContent = File::get(base_path('composer.json'));
        $phpunitXmlFileContent = $this->getPhpunitXmlFileContent();


        try {
            $this->removeMicroserviceFromComposer($microserviceName);
            $this->removeTestDirectoriesToPhpunitXmlFile($microserviceDirectory);
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

    private function removeMicroserviceFromComposer(string $microserviceName): void
    {
        $command = array_merge($this->composer->findComposer(), ['remove', $microserviceName]);

        $process = (new Process($command, base_path('')))->setTimeout(null);

        $process->run(function (string $type, string $data) {
            $this->info($data);
        });

        $content = json_decode(File::get(base_path('composer.json')), true);
        $content['repositories'] = collect($content['repositories'] ?? [])->filter(fn($repo) => @$repo['microservice_name'] !== $microserviceName)->toArray();
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
        $this->info($this->getMicroserviceFullDirectory($microserviceDirectory));
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
        if(!$name){
            $this->error('Microservice name is required');
            return $this->getMicroserviceMainFolderName();
        }
        return $name;
    }

}
