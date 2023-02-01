<?php

namespace Drmovi\PackageGenerator\Actions\Frameworks;

use Drmovi\PackageGenerator\Contracts\Operation;
use Drmovi\PackageGenerator\Dtos\Configs;

class LaravelPackageGeneration implements Operation
{

    public function __construct(
        private readonly string  $packageComposerName,
        private readonly string  $packageName,
        private readonly string  $packageAbsolutePath,
        private readonly string  $packageNamespace,
        private readonly Configs $configs,
    )
    {
    }

    public function exec(): void
    {
        // TODO: Implement backup() method.
    }

    public function backup(): void
    {
        // TODO: Implement backup() method.
    }

    public function rollback(): void
    {
        // TODO: Implement rollback() method.
    }
}
