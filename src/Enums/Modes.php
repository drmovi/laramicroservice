<?php

namespace Drmovi\MonorepoGenerator\Enums;

use Drmovi\MonorepoGenerator\Utils\EnumUtils;

enum Modes: string
{
    use EnumUtils;

    case MONOLITH = 'monolith';
    case MICROSERVICE = 'microservice';
}
