<?php

namespace Drmovi\MonorepoGenerator\Contracts;

interface State
{
    public function backup(): void;

    public function rollback(): void;
}
