<?php

namespace Drmovi\MonorepoGenerator\Enums;

enum Commands: string
{
    case MONOREPO_INIT = 'monorepo:init';
    case MONOREPO_PACKAGE_CREATE = 'monorepo:package:create';
    case MONOREPO_PACKAGE_DELETE = 'monorepo:package:delete';
}
