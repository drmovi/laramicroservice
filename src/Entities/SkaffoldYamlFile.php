<?php

namespace Drmovi\PackageGenerator\Entities;

use Drmovi\PackageGenerator\Contracts\State;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Yaml\Yaml;

class SkaffoldYamlFile implements State
{

    private ?string $backup = null;

    public function __construct(private readonly string $path)
    {
    }

    public function backup(): void
    {
        $path = $this->path . DIRECTORY_SEPARATOR . 'skaffold.yaml';
        if (file_exists($path)) {
            $this->backup = file_get_contents($path);
        }
    }

    public function rollback(): void
    {
        if ($this->backup) {
            file_put_contents($this->path . DIRECTORY_SEPARATOR . 'skaffold.yaml', $this->backup);
        }
    }

    public function getContent(): array
    {
        return Yaml::parseFile($this->path . DIRECTORY_SEPARATOR . 'skaffold.yaml');
    }

    public function setContent(array $content): void
    {
        file_put_contents($this->path . DIRECTORY_SEPARATOR . 'skaffold.yaml', Yaml::dump($content));
    }

    public function addRequire(string $path): void
    {
        $content = $this->getContent();
        $content['requires'][]['path'] = "./$path";
        $this->setContent($content);
    }

    public function removeRequire(string $path): void
    {
        $content = $this->getContent();
        $requires = array_filter($content['requires'], fn($require) => $require['path'] !== "./$path");
        $content['requires'] = $requires;
        $this->setContent($content);
    }

}
