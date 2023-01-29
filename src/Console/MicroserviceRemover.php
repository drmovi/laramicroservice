<?php

namespace Drmovi\LaraMicroservice\Console;

use Drmovi\LaraMicroservice\Traits\Microservice;
use Illuminate\Console\Command;
use Illuminate\Support\Composer;
use Illuminate\Support\Facades\File;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Yaml\Yaml;

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
        $composerLockFileContent = File::get(base_path('composer.lock'));
        $phpunitXmlFileContent = $this->getPhpunitXmlFileContent();
        $microserviceComposerFileContent = json_decode(File::get($microserviceFullDirectory . '/composer.json'), true);
        $composerPackageName = $microserviceComposerFileContent['name'];
        $microserviceSharedDirectory = base_path($this->getSharedPackageDirectory() . '/services/' . $this->getMicroserviceClassName($microserviceName));
        $skaffoldFileContent = File::get(base_path('skaffold.yaml'));
        try {
            $this->removeMicroserviceFromProjectRootComposer($microserviceName, $composerPackageName);
            $this->removeTestDirectoriesToPhpunitXmlFile($microserviceRelativeDirectory);
            $this->deleteMicroserviceDirectory($microserviceFullDirectory);
            $this->removeDevopsEntry($microserviceRelativeDir);
            $this->deleteMicroserviceSharedDirectory($microserviceSharedDirectory);
            $this->info('Microservice removed successfully');
        } catch (\Exception $e) {
            $this->error($e->getMessage());
            $this->info('Rolling back...');
            $this->restoreComposerFile($composerFileContent, $composerLockFileContent);
            $this->setPhpunitXmlFileContent($phpunitXmlFileContent);
            File::put(base_path('skaffold.yaml'), $skaffoldFileContent);
            $this->info('Rolling back completed.');
        }
    }

    private function removeMicroserviceFromProjectRootComposer(string $microserviceName, string $composerMicroservicePackageName): void
    {
        $this->runComposerCommand([
            'remove',
            $composerMicroservicePackageName,
            '--no-interaction',
        ]);
        $this->runComposerCommand([
            'config',
            'repositories.' . $microserviceName,
            '--unset',
            '--no-interaction',
        ]);
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

    private function deleteMicroserviceSharedDirectory(string $path): void
    {
        File::deleteDirectory($path);
    }

    private function removeDevopsEntry(string $microserviceRelativeDir): void
    {
        $data = Yaml::parseFile(base_path('skaffold.yaml'));
        $data['requires'] = array_filter($data['requires'], fn($key, $value) => $value['path'] !== "./$microserviceRelativeDir/k8s/skaffold.yaml", ARRAY_FILTER_USE_BOTH);
        File::put(base_path('skaffold.yaml'), Yaml::dump($data));
    }

}
