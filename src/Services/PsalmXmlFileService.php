<?php

namespace Drmovi\MonorepoGenerator\Services;

use Drmovi\MonorepoGenerator\Contracts\State;

class PsalmXmlFileService implements State
{

    private ?string $backup = null;

    public function __construct(private string $path)
    {
        $this->path = $this->path . DIRECTORY_SEPARATOR . 'psalm.xml';
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

    private function canOperate(): bool
    {
        return file_exists($this->path);
    }


}
