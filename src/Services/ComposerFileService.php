<?php

namespace Drmovi\MonorepoGenerator\Services;

use Drmovi\MonorepoGenerator\Contracts\State;

class ComposerFileService implements State
{

    private ?string $backup = null;

    public function __construct(
        protected string                   $path,
        protected readonly ComposerService $composer
    )
    {
        $this->path = $this->path . DIRECTORY_SEPARATOR . 'composer.json';
    }

    public function backup(): void
    {
        if (!$this->canOperate()) {
            return;
        }

        $this->backup = file_get_contents($this->path);

    }

    public function rollback(): void
    {
        if (!$this->canOperate()) {
            return;
        }

        file_put_contents($this->path, $this->backup);

    }

    public function getName(): ?string
    {
        if (!$this->canOperate()) {
            return null;
        }
        return $this->getContent()['name'];
    }

    public function getContent(): ?array
    {
        if (!$this->canOperate()) {
            return null;
        }
        return json_decode(file_get_contents($this->path), true);
    }

    public function setContent(array $content): void
    {
        if (!$this->canOperate()) {
            return;
        }
        file_put_contents($this->path, json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    public function addPsr4Namespace(array $namespaces, bool $dev = false): void
    {
        if (!$this->canOperate()) {
            return;
        }
        $data = $this->getContent();
        foreach ($namespaces as $namespace => $path) {
            $data[$dev ? 'autoload-dev' : 'autoload']['psr-4'][$namespace] = $path;
        }
        $this->setContent($data);
    }

    public function addRequires(array $requires, bool $dev = false): void
    {
        if (!$this->canOperate()) {
            return;
        }
        $data = $this->getContent();
        foreach ($requires as $require => $version) {
            $data[$dev ? 'require-dev' : 'require'][$require] = $version;
        }
        $this->setContent($data);
    }
    public function removeRequires(array $requires, bool $dev = false): void
    {
        if (!$this->canOperate()) {
            return;
        }
        $data = $this->getContent();
        foreach ($requires as $require) {
            unset($data[$dev ? 'require-dev' : 'require'][$require]);
        }
        $this->setContent($data);
    }

    public function getPsr4Namespace(string $namespace = null, bool $dev = false): string|array|null
    {
        if (!$this->canOperate()) {
            return null;
        }
        $data = $this->getContent();
        return $namespace ? ($data[$dev ? 'autoload-dev' : 'autoload']['psr-4'][$namespace] ?? null) : $data[$dev ? 'autoload-dev' : 'autoload']['psr-4'];
    }

    public function addScripts(array $scripts): void
    {
        if (!$this->canOperate()) {
            return;
        }
        $data = $this->getContent();
        $data['scripts'] = array_merge_recursive($data['scripts'] ?? [], $scripts);
        $this->setContent($data);
    }

    public function getRequireValue(string $string = null): string|array|null
    {
        if (!$this->canOperate()) {
            return null;
        }
        $data = $this->getContent();
        return $string ? ($data['require'][$string] ?? null) : $data['require'];
    }

    public function getRequireDev(string $string = null): string|array|null
    {
        if (!$this->canOperate()) {
            return null;
        }
        $data = $this->getContent();
        return $string ? ($data['require-dev'][$string] ?? null) : $data['require-dev'];
    }


    public function addRepository(string $name, string $url, bool $dev = false): void
    {
        if (!$this->canOperate()) {
            return;
        }
        $this->composer->runComposerCommand([
            'config',
            "repositories.$name",
            json_encode(['type' => 'path', 'url' => $url]),
            '--working-dir',
            dirname($this->path),
            '--no-interaction'
        ]);
        $repoComposerFile = new ComposerFileService($url, $this->composer);
        $args = [
            'require',
            $repoComposerFile->getName(),
            '--working-dir',
            dirname($this->path),
            '--no-interaction'
        ];
        if ($dev) {
            $args[] = '--dev';
        }
        $this->composer->runComposerCommand($args);
    }

    public function runComposerCommand(array $args)
    {
        $this->composer->runComposerCommand($args + ['--working-dir', dirname($this->path)]);
    }

    protected function canOperate(): bool
    {
        return file_exists($this->path);
    }

    public function setName($name): void
    {
        if (!$this->canOperate()) {
            return;
        }
        $data = $this->getContent();
        $data['name'] = $name;
        $this->setContent($data);
    }

    public function setVersion(string $value): void
    {
        if (!$this->canOperate()) {
            return;
        }
        $data = $this->getContent();
        $data['version'] = $value;
        $this->setContent($data);
    }
}
