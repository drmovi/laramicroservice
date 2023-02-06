<?php

namespace Drmovi\MonorepoGenerator\Services;

use Drmovi\MonorepoGenerator\Contracts\State;
use Nette\Neon\Neon;

class NeonFileService implements State
{

    private ?string $backup = null;

    public function __construct(protected readonly string $path)
    {
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


    public function getContent():? array
    {
        if (!$this->canOperate()) {
            return null;
        }
        return Neon::decodeFile($this->path);
    }

    public function setContent(array $content): void
    {
        if (!$this->canOperate()) {
            return;
        }
        file_put_contents($this->path, Neon::encode($content));
    }


    protected function canOperate(): bool
    {
        return file_exists($this->path);
    }
}
