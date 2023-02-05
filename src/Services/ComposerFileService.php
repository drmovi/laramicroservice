<?php

namespace Drmovi\MonorepoGenerator\Services;

use Drmovi\MonorepoGenerator\Contracts\State;

class ComposerFileService implements State
{

    private ?string $backup = null;

    public function __construct(
        protected readonly string          $path,
        protected readonly ComposerService $composer
    )
    {
    }

    public function backup(): void
    {
        $path = $this->path . DIRECTORY_SEPARATOR . 'composer.json';
        if (file_exists($path)) {
            $this->backup = file_get_contents($path);
        }
    }

    public function rollback(): void
    {
        if ($this->backup) {
            file_put_contents($this->path . DIRECTORY_SEPARATOR . 'composer.json', $this->backup);
        }
    }

    public function getName(): string
    {
        return $this->getContent()['name'];
    }

    public function getContent(): array
    {
        return json_decode(file_get_contents($this->path . DIRECTORY_SEPARATOR . 'composer.json'), true);
    }

    public function setContent(array $content): void
    {
        file_put_contents($this->path . DIRECTORY_SEPARATOR . 'composer.json', json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    public function addPsr4Namespace(array $namespaces, bool $dev = false): void
    {
        $data = $this->getContent();
        foreach ($namespaces as $namespace => $path) {
            $data[$dev ? 'autoload-dev' : 'autoload']['psr-4'][$namespace] = $path;
        }
        $this->setContent($data);
    }

    public function getPsr4Namespace(string $namespace = null, bool $dev = false): string|array|null
    {
        $data = $this->getContent();
        return $namespace ? ($data[$dev ? 'autoload-dev' : 'autoload']['psr-4'][$namespace] ?? null) : $data[$dev ? 'autoload-dev' : 'autoload']['psr-4'];
    }

    public function addScripts(array $scripts): void
    {
        $data = $this->getContent();
        $data['scripts'] = array_merge_recursive($data['scripts'] ?? [], $scripts);
        $this->setContent($data);
    }

    public function getRequireDev(string $string = null): string|array|null
    {
        $data = $this->getContent();
        return $string ? ($data['require-dev'][$string] ?? null) : $data['require-dev'];
    }


    public function addRepository(string $name, string $url, bool $dev = false): void
    {
        $this->composer->runComposerCommand([
            'config',
            "repositories.$name",
            json_encode(['type' => 'path', 'url' => $url]),
            '--working-dir',
            $this->path,
            '--no-interaction'
        ]);
        $repoComposerFile = new ComposerFileService($url, $this->composer);
        $args = [
            'require',
            $repoComposerFile->getName(),
            '--working-dir',
            $this->path,
            '--no-interaction'
        ];
        if ($dev) {
            $args[] = '--dev';
        }
        $this->composer->runComposerCommand($args);
    }

    public function runComposerCommand(array $args)
    {
        $this->composer->runComposerCommand($args + ['--working-dir', $this->path]);
    }
}
