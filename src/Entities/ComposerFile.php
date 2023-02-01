<?php

namespace Drmovi\PackageGenerator\Entities;

use Drmovi\PackageGenerator\Contracts\State;

class ComposerFile implements State
{

    private string $backup;

    public function __construct(private readonly string $path)
    {
    }

    public function backup(): void
    {
        $this->backup = file_get_contents($this->path . DIRECTORY_SEPARATOR . 'composer.json');
    }

    public function rollback(): void
    {
        file_put_contents($this->path . DIRECTORY_SEPARATOR . 'composer.json', $this->backup);
    }

    public function getContent(): array
    {
        return json_decode(file_get_contents($this->path . DIRECTORY_SEPARATOR . 'composer.json'), true);
    }

    public function setContent(array $content): void
    {
        file_put_contents($this->path . DIRECTORY_SEPARATOR . 'composer.json', json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    public function addPsr4Namespace(string $namespace, string $path)
    {
        $data = $this->getContent();
        $data['autoload']['psr-4'][$namespace] = $path;
        $this->setContent($data);
    }
}
