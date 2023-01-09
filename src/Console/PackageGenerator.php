<?php

namespace Drmovi\Larapackager\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Composer;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Process\Process;

class PackageGenerator extends Command
{

    protected $signature = 'package:scaffold';


    protected $description = 'Generate a package skeleton inside a laravel project';

    private const PACKAGE_DIRECTORY = 'packages';

    public function __construct(private readonly Composer $composer)
    {
        parent::__construct();
    }

    public function handle(): void
    {
        $packageName = $this->getPackageName();
        $packageVersion = $this->getPackageVersion();
        $packageDescription = $this->ask('write short description of your package');
        $packageNamespace = $this->getPackageNamespace($packageName);
        $packageDirectory = $this->getPackageDirectory($packageName);
        $packageFullDirectory = base_path($packageDirectory);
        $loadItLocally = $this->confirm('Do you want to load it locally in composer?', true);
        $composerFileContent = File::get(base_path('composer.json'));
        try{

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
            $this->addPackageToComposer($packageName, $packageDirectory, $loadItLocally);
        }catch (\Throwable $e){
            $this->error($e->getMessage());
            $this->info('Rolling back...');
            $this->deletePackageDirectory($packageFullDirectory);
            $this->restoreComposerFile($composerFileContent);
        }


    }

    private function getPackageName(): string
    {
        $name = $this->ask('What is the composer name of your package?');
        if (!preg_match('{^[a-z0-9_.-]+/[a-z0-9_.-]+$}D', $name)) {
            $this->error('Invalid composer name. It should be lowercase and have a vendor name, a forward slash, and a package name, matching: [a-z0-9_.-]+/[a-z0-9_.-]+');
            return $this->getPackageName();
        }
        return $name;
    }

    private function getSuggestedNNamespace(string $packageName): string
    {
        $name = Str::of($packageName)->explode('/');
        return Str::of($name[0])->studly()->append('\\')->append(Str::of($name[1])->studly());
    }

    private function getPackageNamespace(string $packageName): string
    {
        $namespace = $this->ask('write your package namespace', $this->getSuggestedNNamespace($packageName));
        if (!preg_match('/^[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff\\\]*$/', $namespace)) {
            $this->error('Invalid namespace. It should be a valid php namespace, matching: ^[a-zA-Z_\x80-\xff][a-zA-Z0-9_\\x80-\xff]*$');
            return $this->getPackageNamespace($packageName);
        }
        return $namespace;

    }

    private function getDefaultPackageDirectory(string $packageName): string
    {
        $name = Str::of($packageName)->explode('/');
        return self::PACKAGE_DIRECTORY . '/' . $name[1];
    }

    private function getPackageClassName(string $packageName): string
    {
        return Str::studly(Str::of($packageName)->explode('/')->pop());
    }

    private function getPackageFileName(string $packageName): string
    {
        return Str::replace('_', '-', Str::kebab(Str::of($packageName)->explode('/')->pop()));
    }


    private function getPackageDirectory(string $packageName): string
    {
        $dir = $this->ask('write package directory relative to project root', $this->getDefaultPackageDirectory($packageName));
        if (!preg_match('/^(.+)\/([^\/]+)$/', $dir)) {
            $this->error('package directory should match ^(.+)\/([^\/]+)$');
            return $this->getPackageDirectory($packageName);
        }
        return $dir;
    }

    private function createPackageDirectory(string $packageDirectory): void
    {
        File::copyDirectory(__DIR__.'/../../stub', $packageDirectory);
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

    private function addPackageToComposer(string $packageName, string $packageDirectory, bool $isLocal): void
    {
        if ($isLocal) {
            $content = json_decode(File::get(base_path('composer.json')));
            if (!collect($content->repositories ?? [])->where('url', $packageDirectory)->first()) {
                $this->sanitizeRepositories($content);
                $content->repositories[] = ['type' => 'path', 'url' => './' . $packageDirectory, 'package_name' => $packageName];
                File::put(base_path('composer.json'), json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            }
        }
        $command = array_merge($this->composer->findComposer(), ['require', $packageName]);

        $process = (new Process($command, base_path('')))->setTimeout(null);

        $process->run(function (string $type, string $data) {
            $this->info($data);
        });
    }

    private function getPackageVersion(): string
    {
        return $this->ask('what\'s your package version?', '1.0.0');
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

    /**
     * @param string $packageFullDirectory
     * @return void
     */
    private function deletePackageDirectory(string $packageFullDirectory): void
    {
        File::deleteDirectory($packageFullDirectory);
    }

    private function restoreComposerFile(string $composerFileContent):void
    {
        File::put(base_path('composer.json'), $composerFileContent);
        $command = array_merge($this->composer->findComposer(), ['install']);

        $process = (new Process($command, base_path()))->setTimeout(null);

        $process->run(function (string $type, string $data) {
            $this->info($data);
        });
    }
}
