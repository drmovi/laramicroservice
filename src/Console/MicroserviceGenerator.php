<?php

namespace Drmovi\LaraMicroservice\Console;

use Drmovi\LaraMicroservice\Traits\Package;
use Illuminate\Console\Command;
use Illuminate\Support\Composer;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Process\Process;

class MicroserviceGenerator extends Command
{
    use Package;

    protected $signature = 'microservice:scaffold';


    protected $description = 'Generate a microservice skeleton inside a laravel project';


    public function __construct(private readonly Composer $composer)
    {
        parent::__construct();
    }

    public function handle(): void
    {
        $packageName = $this->getPackageName();
        $packageVersion = $this->getPackageVersion();
        $packageDescription = $this->ask('write short description of your microservice');
        $packageNamespace = $this->getPackageNamespace($packageName);
        $packageDirectory = $this->getPackageDirectory($packageName);
        $packageFullDirectory = $this->getPackageFullDirectory($packageDirectory);
        $composerFileContent = File::get(base_path('composer.json'));
        $phpunitXmlFileContent = $this->getPhpunitXmlFileContent();
        $laravelVersion = $this->getLaravelVersion();
        try {

            $this->createPackage(
                $packageFullDirectory,
                $packageName,
                $packageVersion,
                $packageDescription,
                $packageNamespace,
                $packageDirectory,
                $laravelVersion
            );
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            $this->info('Rolling back...');
            $this->deletePackageDirectory($packageFullDirectory);
            $this->restoreComposerFile($composerFileContent);
            $this->setPhpunitXmlFileContent($phpunitXmlFileContent);
        }


    }


    private function getSuggestedNNamespace(string $packageName): string
    {
        $name = Str::of($packageName)->explode('/');
        return Str::of($name[0])->studly()->append('\\')->append(Str::of($name[1])->studly());
    }

    private function getPackageNamespace(string $packageName): string
    {
        $namespace = $this->ask('write your microservice namespace', $this->getSuggestedNNamespace($packageName));
        if (!preg_match('/^[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff\\\]*$/', $namespace)) {
            $this->error('Invalid namespace. It should be a valid php namespace, matching: ^[a-zA-Z_\x80-\xff][a-zA-Z0-9_\\x80-\xff]*$');
            return $this->getPackageNamespace($packageName);
        }
        return $namespace;

    }

    private function getPackageClassName(string $packageName): string
    {
        return Str::studly(Str::of($packageName)->explode('/')->pop());
    }

    private function getPackageFileName(string $packageName): string
    {
        return Str::replace('_', '-', Str::kebab(Str::of($packageName)->explode('/')->pop()));
    }


    private function createPackageDirectory(string $packageDirectory): void
    {
        File::copyDirectory(__DIR__ . '/../../stub', $packageDirectory);
    }

    private function preparePackageFiles(string $packageFullDirectory, array $placeholders): void
    {
        $files = File::allFiles($packageFullDirectory);
        foreach ($files as $file) {
            $this->preparePackageFile($file, $placeholders);
        }
    }

    private function preparePackageFile(SplFileInfo $file, array $placeholders): void
    {
        File::put($file->getPathname(), Str::replace(array_keys($placeholders), array_values($placeholders), $file->getContents()));
        File::move($file->getPathname(), $file->getPath() . '/' . Str::replace(array_keys($placeholders), array_values($placeholders), $file->getFilename()));
    }

    private function addPackageToComposer(string $packageName, string $packageDirectory): void
    {

        $content = json_decode(File::get(base_path('composer.json')));
        if (!collect($content->repositories ?? [])->where('url', $packageDirectory)->first()) {
            $this->sanitizeRepositories($content);
            $content->repositories[] = ['type' => 'path', 'url' => './' . $packageDirectory, 'package_name' => $packageName];
            File::put(base_path('composer.json'), json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        $command = array_merge($this->composer->findComposer(), ['require', $packageName]);

        $process = (new Process($command, base_path('')))->setTimeout(null);

        $process->run(function (string $type, string $data) {
            $this->info($data);
        });
    }

    private function getPackageVersion(): string
    {
        return $this->ask('what\'s your microservice version?', '1.0.0');
    }

    private function sanitizeRepositories(\stdClass $content): void
    {
        $existingRepos = $this->filterRepos($content, true);
        $noneExistingRepos = $this->filterRepos($content, false);
        $content->repositories = $existingRepos;
        foreach ($noneExistingRepos as $repo) {
            unset($content->require->{$repo->package_name});
        }
    }

    private function filterRepos(\stdClass $content, bool $existing): array
    {
        return collect($content->repositories ?? [])->filter(function ($repository) use ($existing) {
            if ($repository->type !== 'path') {
                return true;
            }
            $isDir = File::isDirectory(Str::startsWith($repository->url, './') ? base_path($repository->url) : $repository->url);
            return $existing ? $isDir : !$isDir;
        })->toArray();
    }

    private function addTestDirectoriesToPhpunitXmlFile(string $packageDirectory): void
    {
        $crawler = new Crawler(File::get(base_path('phpunit.xml')));
        $crawler->filterXPath('//phpunit/testsuites/testsuite[@name="Unit"]')->each(function (Crawler $node) use ($crawler, $packageDirectory) {
            $child = $crawler->getNode(0)->parentNode->createElement('directory', './' . $packageDirectory . '/tests/Unit');
            $child->setAttribute('suffix', 'Test.php');
            $node->getNode(0)->appendChild($child);
        });
        $crawler->filterXPath('//phpunit/testsuites/testsuite[@name="Feature"]')->each(function (Crawler $node) use ($crawler, $packageDirectory) {
            $child = $crawler->getNode(0)->parentNode->createElement('directory', './' . $packageDirectory . '/tests/Feature');
            $child->setAttribute('suffix', 'Test.php');
            $node->getNode(0)->appendChild($child);
        });
        $this->setPhpunitXmlFileContent($crawler->getNode(0)->ownerDocument->saveXML());
    }


    private function createPackage(
        string $packageFullDirectory,
        string $packageName,
        string $packageVersion,
        mixed  $packageDescription,
        string $packageNamespace,
        string $packageDirectory,
        string $laravelVersion
    ): void
    {
        $this->createPackageDirectory($packageFullDirectory);
        $this->preparePackageFiles(
            $packageFullDirectory,
            [
                '{{PACKAGE_COMPOSER_NAME}}' => $packageName,
                '{{PACKAGE_VERSION}}' => $packageVersion,
                '{{PACKAGE_DESCRIPTION}}' => $packageDescription,
                '{{PACKAGE_COMPOSER_NAMESPACE}}' => str_replace('\\', '\\\\', $packageNamespace),
                '{{PACKAGE_NAMESPACE}}' => $packageNamespace,
                '{{PACKAGE_CLASS_NAME}}' => $this->getPackageClassName($packageName),
                '{{PACKAGE_FILE_NAME}}' => $this->getPackageFileName($packageName),
            ]
        );
        $this->updateComposerFile($packageDirectory, $laravelVersion);
        $this->addPackageToComposer($packageName, $packageDirectory);
        $this->addTestDirectoriesToPhpunitXmlFile($packageDirectory);
    }

    private function getLaravelVersion(): string
    {
        return floor((floatval($this->laravel->version()))). '.x';
    }

    private function updateComposerFile(string $packageDirectory, string $laravelVersion):void
    {
        $laravelComposer = Http::get(config('laramicroservice.laravel_repo_url') . '/'.$laravelVersion . '/composer.json')->throw()->json();
        $packageComposerContent = json_decode(File::get(base_path($packageDirectory . '/composer.json')), true);
        $laravelComposer['keywords'] = [];
        $laravelComposer['description'] = '';
        $laravelComposer['autoload']['psr-4'] = [];
        $laravelComposer['autoload-dev']['psr-4'] = [];
        $composerContent  = array_replace_recursive($laravelComposer,$packageComposerContent);
        File::put(base_path($packageDirectory . '/composer.json'), json_encode($composerContent, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
