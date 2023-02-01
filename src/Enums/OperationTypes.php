<?php

namespace Drmovi\PackageGenerator\Enums;

enum OperationTypes : string
{
    case PackageGenerator = 'Generation';
    case PackageRemover = 'Removal';
}
