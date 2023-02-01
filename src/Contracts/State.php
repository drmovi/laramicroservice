<?php

namespace Drmovi\PackageGenerator\Contracts;

interface State
{
    public function backup(): void;

    public function rollback(): void;
}
