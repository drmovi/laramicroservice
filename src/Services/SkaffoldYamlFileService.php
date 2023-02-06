<?php

namespace Drmovi\MonorepoGenerator\Services;

use Drmovi\MonorepoGenerator\Contracts\State;
use Symfony\Component\Yaml\Yaml;

class SkaffoldYamlFileService implements State
{

    private ?string $backup = null;

    public function __construct(private string $path)
    {
        $this->path = $this->path . DIRECTORY_SEPARATOR . 'skaffold.yaml';
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

    public function getContent(): ?array
    {
        if (!$this->canOperate()) {
            return null;
        }
        return Yaml::parseFile($this->path);
    }

    public function setContent(array $content): void
    {
        if (!$this->canOperate()) {
            return;
        }
        file_put_contents($this->path, Yaml::dump($content));
    }

    public function addRequire(string $path): void
    {
        if (!$this->canOperate()) {
            return;
        }
        $content = $this->getContent();
        $content['requires'][]['path'] = "./$path";
        $this->setContent($content);
    }

    public function removeRequire(string $path): void
    {
        if (!$this->canOperate()) {
            return;
        }
        $content = $this->getContent();
        $requires = array_filter($content['requires'], fn($require) => $require['path'] !== "./$path");
        $content['requires'] = $requires;
        $this->setContent($content);
    }


    private function canOperate(): bool
    {
        return file_exists($this->path);
    }

}
