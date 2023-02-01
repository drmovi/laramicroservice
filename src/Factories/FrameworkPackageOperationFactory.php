<?php

namespace Drmovi\PackageGenerator\Factories;

use Drmovi\PackageGenerator\Contracts\Operation;
use Drmovi\PackageGenerator\Enums\OperationTypes;

class FrameworkPackageOperationFactory
{

    public function make(string $framework, array $generatorArgs, OperationTypes $operation): Operation
    {
        $framework = ucfirst($framework);
        $class = "Drmovi\\PackageGenerator\\Actions\\Frameworks\\{$framework}Package{$operation->value}";
        return new $class(...$generatorArgs);
    }
}
