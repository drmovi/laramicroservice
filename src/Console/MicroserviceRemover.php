<?php

namespace Drmovi\LaraMicroservice\Console;

use Drmovi\LaraMicroservice\Traits\Package;
use Illuminate\Console\Command;
use Illuminate\Support\Composer;
use Illuminate\Support\Facades\File;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Process\Process;

class MicroserviceRemover extends Command
{

    use Package;

    protected $signature = 'microservice:remove';


    protected $description = 'Remove a microservice skeleton inside a laravel project';


    public function __construct(private readonly Composer $composer)
    {
        parent::__construct();
    }


    public function handle(): void
    {
        [$packageName, $packageDirectory] = $this->getPackageData();
        $packageFullDirectory = base_path($packageDirectory);
        $composerFileContent = File::get(base_path('composer.json'));
        $phpunitXmlFileContent = $this->getPhpunitXmlFileContent();


        try {
            $this->removePackageFromComposer($packageName);
            $this->removeTestDirectoriesToPhpunitXmlFile($packageFullDirectory);
            $this->deletePackageDirectory($packageFullDirectory);
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

    private function removePackageFromComposer(string $packageName): void
    {
        $command = array_merge($this->composer->findComposer(), ['remove', $packageName]);

        $process = (new Process($command, base_path('')))->setTimeout(null);

        $process->run(function (string $type, string $data) {
            $this->info($data);
        });

        $content = json_decode(File::get(base_path('composer.json')), true);
        $content['repositories'] = collect($content['repositories'] ?? [])->filter(fn($repo) => @$repo['package_name'] !== $packageName)->toArray();
        File::put(base_path('composer.json'), json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    }

    private function removeTestDirectoriesToPhpunitXmlFile(string $packageDirectory): void
    {
        $crawler = new Crawler(File::get(base_path('phpunit.xml')));
        $crawler->filterXPath('//phpunit/testsuites/testsuite//*')->each(function (Crawler $node) use ($packageDirectory) {
            if (in_array($node->text(), ['./' . $packageDirectory . '/tests/Unit', './' . $packageDirectory . '/tests/Feature'])) {
                $node->getNode(0)->parentNode->removeChild($node->getNode(0));
            };
        });
        $this->setPhpunitXmlFileContent($crawler->getNode(0)->ownerDocument->saveXML());
    }


    private function getPackageData(): array
    {
        $packageName = $this->getPackageName();
        $packageDirectory = $this->getPackageDirectory($packageName);
        $this->info($this->getPackageFullDirectory($packageDirectory));
        if (!File::isDirectory($this->getPackageFullDirectory($packageDirectory))) {
            $this->error('Microservice not found');
            return $this->getPackageData();
        }
        return [
            $packageName,
            $packageDirectory
        ];
    }

}
