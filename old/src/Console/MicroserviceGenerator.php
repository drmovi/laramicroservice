<?php

namespace Drmovi\LaraMicroservice\Console;

use Drmovi\LaraMicroservice\Traits\Microservice;
use Illuminate\Console\Command;
use Illuminate\Support\Composer;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Yaml\Yaml;

class MicroserviceGenerator extends Command
{
    use Microservice {
        getComposerPackageName as baseGetComposerPackageName;
    }

    protected $signature = 'microservice:scaffold';


    protected $description = 'Generate a microservice skeleton inside a laravel project';


    public function __construct(private readonly Composer $composer)
    {
        parent::__construct();
    }

    public function handle(): void
    {
        $microserviceComposerPackageName = $this->getComposerPackageName();
        $microserviceName = $this->getMicroserviceName($microserviceComposerPackageName);
        $microserviceVersion = $this->getMicroserviceVersion();
        $microserviceDescription = $this->ask('write short description of your microservice');
        $microserviceNamespace = $this->getMicroserviceNamespace($microserviceComposerPackageName);
        $microserviceDirectory = $this->getMicroserviceDirectory($microserviceComposerPackageName);
        $microserviceFullDirectory = $this->getMicroserviceFullDirectory($microserviceDirectory);
        $composerFileContent = File::get(base_path('composer.json'));
        $composerLockFileContent = File::get(base_path('composer.lock'));
        $phpunitXmlFileContent = $this->getPhpunitXmlFileContent();
        $sharedPackageDirectory = $this->getSharedPackageDirectory();
        try {

            $this->createMicroservice(
                $microserviceFullDirectory,
                $microserviceName,
                $microserviceComposerPackageName,
                $microserviceVersion,
                $microserviceDescription,
                $microserviceNamespace,
                $microserviceDirectory,
            );
            $this->createSharedEntries($sharedPackageDirectory, $microserviceComposerPackageName);
            $this->createDevopsFiles($microserviceDirectory);

        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            $this->info('Rolling back...');
            $this->deleteMicroserviceDirectory($microserviceFullDirectory);
            $this->restoreComposerFile($composerFileContent, $composerLockFileContent);
            $this->setPhpunitXmlFileContent($phpunitXmlFileContent);
            $this->rollbackSharedMicroserviceFiles($sharedPackageDirectory, $microserviceComposerPackageName);
        }


    }


    private function getComposerPackageName(): string
    {
        $microserviceName = $this->baseGetComposerPackageName();
        if (File::isDirectory($this->getMicroserviceFullDirectory($this->getMicroserviceDirectory($microserviceName)))) {
            $this->error('The microservice already exists. Choose another name');
            return $this->getComposerPackageName();
        }
        return $microserviceName;
    }


    private function getSuggestedNamespace(string $microserviceName): string
    {
        $name = Str::of($microserviceName)->explode('/');
        return Str::of($name[0])->studly()->append('\\')->append(Str::of($name[1])->studly());
    }

    private function getMicroserviceNamespace(string $microserviceName): string
    {
        $namespace = $this->ask('write your microservice namespace', $this->getSuggestedNamespace($microserviceName));
        if (!preg_match('/^[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff\\\]*$/', $namespace)) {
            $this->error('Invalid namespace. It should be a valid php namespace, matching: ^[a-zA-Z_\x80-\xff][a-zA-Z0-9_\\x80-\xff]*$');
            return $this->getMicroserviceNamespace($microserviceName);
        }
        return $namespace;
    }

    private function getMicroserviceFileName(string $microserviceName): string
    {
        return Str::replace('_', '-', Str::kebab(Str::of($microserviceName)->explode('/')->pop()));
    }


    private function createMicroserviceDirectory(string $microserviceDirectory): void
    {
        File::copyDirectory(__DIR__ . '/../../stubs/microservice', $microserviceDirectory);
    }

    private function prepareDirectoryFiles(string $microserviceFullDirectory, array $placeholders): void
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

    private function addMicroserviceToProjectRootComposer(string $microserviceName, string $composerMicroserviceName, string $microserviceDirectory): void
    {
        $this->runComposerCommand([
            'config',
            "repositories.$microserviceName",
            json_encode(['type' => 'path', 'url' => './' . $microserviceDirectory]),
            '--no-interaction'
        ]);
        $this->runComposerCommand([
            'require',
            $composerMicroserviceName,
            '--no-interaction',
            '--no-install'
        ]);
    }

    private function getMicroserviceVersion(): string
    {
        return $this->ask('what\'s your microservice version?', '1.0.0');
    }




    private function createMicroservice(
        string $microserviceFullDirectory,
        string $microserviceName,
        string $composerMicroserviceName,
        string $microserviceVersion,
        mixed  $microserviceDescription,
        string $microserviceNamespace,
        string $microserviceDirectory,
    ): void
    {
        $this->createMicroserviceDirectory($microserviceFullDirectory);
        $this->prepareDirectoryFiles(
            $microserviceFullDirectory,
            [
                '{{PROJECT_COMPOSER_NAME}}' => $composerMicroserviceName,
                '{{PROJECT_VERSION}}' => $microserviceVersion,
                '{{PROJECT_DESCRIPTION}}' => $microserviceDescription,
                '{{PROJECT_COMPOSER_NAMESPACE}}' => str_replace('\\', '\\\\', $microserviceNamespace),
                '{{PROJECT_NAMESPACE}}' => $microserviceNamespace,
                '{{PROJECT_CLASS_NAME}}' => $this->getMicroserviceClassName($composerMicroserviceName),
                '{{PROJECT_FILE_NAME}}' => $this->getMicroserviceFileName($composerMicroserviceName),
            ]
        );
        $this->addMicroserviceToProjectRootComposer($microserviceName, $composerMicroserviceName, $microserviceDirectory);
        $this->addTestDirectoriesToPhpunitXmlFile($microserviceDirectory);
    }

    private function createSharedEntries(string $sharedDirectory, string $microserviceComposerPackageName): void
    {
        $sharedFullDirectory = base_path($sharedDirectory);
        $sharedComposerPackageName = 'app/shared';
        $sharedName = $this->getMicroserviceName($sharedComposerPackageName);
        $sharedMicroserviceNamespace = 'App\Shared';
        $sharedMicroserviceDescription = 'Shared package used in all microservices';
        $sharedMicroserviceVersion = '1.0.0';
        if (!File::isDirectory($sharedFullDirectory)) {
            $this->createMicroservice(
                $sharedFullDirectory,
                $sharedName,
                $sharedComposerPackageName,
                $sharedMicroserviceVersion,
                $sharedMicroserviceDescription,
                $sharedMicroserviceNamespace,
                $sharedDirectory,
            );
            File::makeDirectory($sharedFullDirectory . '/services');
            $sharedComposerFile = json_decode(File::get($sharedFullDirectory . '/composer.json'), true);
            $sharedComposerFile['autoload']['psr-4'][$sharedMicroserviceNamespace . '\\Services\\'] = 'services/';
            File::put($sharedFullDirectory . '/composer.json', json_encode($sharedComposerFile, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            File::deleteDirectory($sharedFullDirectory . '/k8s');
            File::copyDirectory(__DIR__ . '/../../stubs/shared/app', $sharedFullDirectory . '/app');
            File::copyDirectory(__DIR__ . '/../../stubs/shared/routes', $sharedFullDirectory . '/routes');
            $this->prepareDirectoryFiles($sharedFullDirectory, ['{{PROJECT_NAMESPACE}}' => $sharedMicroserviceNamespace]);
        }
        File::copyDirectory(__DIR__ . '/../../stubs/shared/services', $sharedFullDirectory . '/services');
        $microserviceClassName = $this->getMicroserviceClassName($microserviceComposerPackageName);
        $shardMicroserviceFullPath = $sharedFullDirectory . '/services/' . $microserviceClassName;
        File::move($sharedFullDirectory . '/services/{{PROJECT_CLASS_NAME}}', $shardMicroserviceFullPath);
        $this->prepareDirectoryFiles($shardMicroserviceFullPath,
            [
                '{{PROJECT_CLASS_NAME}}' => $microserviceClassName,
            ]
        );
    }

    private function rollbackSharedMicroserviceFiles(string $sharedDirectory, string $microserviceClassName): void
    {
        File::deleteDirectory(base_path($sharedDirectory . '/services/{{PROJECT_CLASS_NAME}}'));
        File::deleteDirectory(base_path($sharedDirectory . '/services/' . $microserviceClassName));
    }

    private function createDevopsFiles(string $microserviceDirectory): void
    {
        if(!File::exists(base_path('Dockerfile'))) {
            File::copyDirectory(__DIR__ . '/../../stubs/project', base_path());
        }
        $data = Yaml::parseFile(base_path('skaffold.yaml'));
        $data['requires'][] = ['path' => "./$microserviceDirectory/k8s/skaffold.yaml"];
        File::put(base_path('skaffold.yaml'), Yaml::dump($data));
    }
}
