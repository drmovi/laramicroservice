<?php

namespace Drmovi\MonorepoGenerator\Services;

use Drmovi\MonorepoGenerator\Contracts\State;
use Nette\Neon\Neon;

class NeonFileService implements State
{

    private ?string $backup = null;

    public function __construct(private readonly string $path)
    {
    }

    public function backup(): void
    {
        if (file_exists($this->path)) {
            $this->backup = file_get_contents($this->path);
        }
    }

    public function rollback(): void
    {
        if ($this->backup) {
            file_put_contents($this->path , $this->backup);
        }
    }


    public function getContent():array
    {
        return Neon::decodeFile($this->path);
    }

    public function setContent(array $content): void
    {
        file_put_contents($this->path, Neon::encode($content));
    }
}
