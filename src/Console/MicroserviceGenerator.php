<?php

namespace Drmovi\LaraMicroservice\Console;

use Composer\Console\Application;
use Drmovi\LaraMicroservice\Traits\Microservice;
use Illuminate\Console\Command;
use Illuminate\Support\Composer;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Finder\SplFileInfo;

class MicroserviceGenerator extends Command
{
    use Microservice {
        getMicroserviceName as baseGetMicroserviceName;
    }

    protected $signature = 'microservice:scaffold';


    protected $description = 'Generate a microservice skeleton inside a laravel project';


    public function __construct(private readonly Composer $composer)
    {
        parent::__construct();
    }

    public function handle(): void
    {
        $microserviceName = $this->getMicroserviceName();
        $microserviceVersion = $this->getMicroserviceVersion();
        $microserviceDescription = $this->ask('write short description of your microservice');
        $microserviceNamespace = $this->getMicroserviceNamespace($microserviceName);
        $microserviceDirectory = $this->getMicroserviceDirectory($microserviceName);
        $microserviceFullDirectory = $this->getMicroserviceFullDirectory($microserviceDirectory);
        $composerFileContent = File::get(base_path('composer.json'));
        $phpunitXmlFileContent = $this->getPhpunitXmlFileContent();
        $laravelVersion = $this->getLaravelVersion();
        try {

            $this->createMicroservice(
                $microserviceFullDirectory,
                $microserviceName,
                $microserviceVersion,
                $microserviceDescription,
                $microserviceNamespace,
                $microserviceDirectory,
                $laravelVersion
            );
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            $this->info('Rolling back...');
            $this->deleteMicroserviceDirectory($microserviceFullDirectory);
            $this->restoreComposerFile($composerFileContent);
            $this->setPhpunitXmlFileContent($phpunitXmlFileContent);
        }


    }


    private function getMicroserviceName(): string
    {
        $microserviceName = $this->baseGetMicroserviceName();
        if (File::isDirectory($this->getMicroserviceFullDirectory($this->getMicroserviceDirectory($microserviceName)))) {
            $this->error('The microservice already exists. Choose another name');
            return $this->getMicroserviceName();
        }
        return $microserviceName;
    }


    private function getSuggestedNNamespace(string $microserviceName): string
    {
        $name = Str::of($microserviceName)->explode('/');
        return Str::of($name[0])->studly()->append('\\')->append(Str::of($name[1])->studly());
    }

    private function getMicroserviceNamespace(string $microserviceName): string
    {
        $namespace = $this->ask('write your microservice namespace', $this->getSuggestedNNamespace($microserviceName));
        if (!preg_match('/^[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff\\\]*$/', $namespace)) {
            $this->error('Invalid namespace. It should be a valid php namespace, matching: ^[a-zA-Z_\x80-\xff][a-zA-Z0-9_\\x80-\xff]*$');
            return $this->getMicroserviceNamespace($microserviceName);
        }
        return $namespace;

    }

    private function getMicroserviceClassName(string $microserviceName): string
    {
        return Str::studly(Str::of($microserviceName)->explode('/')->pop());
    }

    private function getMicroserviceFileName(string $microserviceName): string
    {
        return Str::replace('_', '-', Str::kebab(Str::of($microserviceName)->explode('/')->pop()));
    }


    private function createMicroserviceDirectory(string $microserviceDirectory): void
    {
        File::copyDirectory(__DIR__ . '/../../stub', $microserviceDirectory);
    }

    private function prepareMicroserviceFiles(string $microserviceFullDirectory, array $placeholders): void
    {
        $files = File::allFiles($microserviceFullDirectory);
        foreach ($files as $file) {
            $this->prepareMicroserviceFile($file, $placeholders);
        }
    }

    private function prepareMicroserviceFile(SplFileInfo $file, array $placeholders): void
    {
        File::put($file->getPathname(), Str::replace(array_keys($placeholders), array_values($placeholders), $file->getContents()));
        File::move($file->getPathname(), $file->getPath() . '/' . Str::replace(array_keys($placeholders), array_values($placeholders), $file->getFilename()));
    }

    private function addMicroserviceToComposer(string $composerMicroserviceName, string $microserviceDirectory): void
    {

        $content = json_decode(File::get(base_path('composer.json')));
        if (!collect($content->repositories ?? [])->where('url', $microserviceDirectory)->first()) {
            $content->repositories[] = ['type' => 'path', 'url' => './' . $microserviceDirectory];
            File::put(base_path('composer.json'), json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }
        $application = new Application();
        $application->setAutoExit(false);
        $result = $application->run(new ArgvInput(['composer', 'require', $composerMicroserviceName, '--no-interaction']), $this->output);
        if ($result > 0) {
            throw new \Exception('Error while adding microservice to composer');
        }
    }

    private function getMicroserviceVersion(): string
    {
        return $this->ask('what\'s your microservice version?', '1.0.0');
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


    private function createMicroservice(
        string $microserviceFullDirectory,
        string $microserviceName,
        string $microserviceVersion,
        mixed  $microserviceDescription,
        string $microserviceNamespace,
        string $microserviceDirectory,
        string $laravelVersion
    ): void
    {
        $this->createMicroserviceDirectory($microserviceFullDirectory);
        $this->prepareMicroserviceFiles(
            $microserviceFullDirectory,
            [
                '{{PROJECT_COMPOSER_NAME}}' => $microserviceName,
                '{{PROJECT_VERSION}}' => $microserviceVersion,
                '{{PROJECT_DESCRIPTION}}' => $microserviceDescription,
                '{{PROJECT_COMPOSER_NAMESPACE}}' => str_replace('\\', '\\\\', $microserviceNamespace),
                '{{PROJECT_NAMESPACE}}' => $microserviceNamespace,
                '{{PROJECT_CLASS_NAME}}' => $this->getMicroserviceClassName($microserviceName),
                '{{PROJECT_FILE_NAME}}' => $this->getMicroserviceFileName($microserviceName),
            ]
        );
        $this->updateComposerFile($microserviceDirectory, $laravelVersion);
        $this->addMicroserviceToComposer($microserviceName, $microserviceDirectory);
        $this->addTestDirectoriesToPhpunitXmlFile($microserviceDirectory);
    }

    private function getLaravelVersion(): string
    {
        return floor((floatval($this->laravel->version()))) . '.x';
    }

    private function updateComposerFile(string $microserviceDirectory, string $laravelVersion): void
    {
        $laravelComposer = Http::get(config('laramicroservice.laravel_repo_url') . '/' . $laravelVersion . '/composer.json')->throw()->json();
        $microserviceComposerContent = json_decode(File::get(base_path($microserviceDirectory . '/composer.json')), true);
        $laravelComposer['keywords'] = [];
        $laravelComposer['description'] = '';
        $laravelComposer['autoload']['psr-4'] = [];
        $laravelComposer['autoload-dev']['psr-4'] = [];
        $composerContent = array_replace_recursive($laravelComposer, $microserviceComposerContent);
        File::put(base_path($microserviceDirectory . '/composer.json'), json_encode($composerContent, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
