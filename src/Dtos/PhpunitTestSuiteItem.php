<?php

namespace Drmovi\MonorepoGenerator\Dtos;

class PhpunitTestSuiteItem
{

    public function __construct(
        public readonly string  $path,
        public readonly string $testSuiteName,
        public readonly ?string $suffix = null,
    )
    {
    }
}
