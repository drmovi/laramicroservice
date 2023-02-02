<?php

namespace Drmovi\PackageGenerator\Enums;

enum OperationTypes : string
{
    case PACKAGE_CREATION = 'creation';
    case PACKAGE_DELETION = 'deletion';
    case UNSPECIFIED = 'unspecified';
}
