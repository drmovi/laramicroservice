<?php

namespace Drmovi\MonorepoGenerator\Dtos;

use Drmovi\MonorepoGenerator\Services\ComposerFileService;
use Drmovi\MonorepoGenerator\Services\PhpstanNeonService;
use Drmovi\MonorepoGenerator\Services\PhpUnitXmlFileService;
use Drmovi\MonorepoGenerator\Services\SkaffoldYamlFileService;

class PackageData
{

    public function __construct(
        public ComposerFileService     $rootComposerFileService,
        public PhpUnitXmlFileService   $rootPhpunitXmlFileService,
        public SkaffoldYamlFileService $rootSkaffoldYamlFileService,
        public PhpstanNeonService      $phpstanNeonFileService,
        public string                  $packageName,
        public string                  $packageRelativePath,
        public string                  $packageAbsolutePath,
        public string                  $packageNamespace,
        public string                  $packageSkaffoldYamlFileRelativePath,
        public string                  $packageComposerName,
        public bool                    $isSharedPackage = false,
    )
    {
    }


}
