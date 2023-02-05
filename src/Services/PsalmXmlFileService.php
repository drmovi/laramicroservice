<?php

namespace Drmovi\MonorepoGenerator\Services;

use Drmovi\MonorepoGenerator\Contracts\State;

class PsalmXmlFileService implements State
{

    private ?string $backup = null;

    public function __construct(private readonly string $path)
    {
    }


    public function backup(): void
    {
        $path = $this->path . DIRECTORY_SEPARATOR . 'psalm.xml';
        if (file_exists($path)) {
            $this->backup = file_get_contents($path);
        }
    }

    public function rollback(): void
    {
        if ($this->backup) {
            file_put_contents($this->path . DIRECTORY_SEPARATOR . 'psalm.xml', $this->backup);
        }
    }
}
