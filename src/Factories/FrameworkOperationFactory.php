<?php

namespace Drmovi\MonorepoGenerator\Factories;

use Drmovi\MonorepoGenerator\Actions\Frameworks\LaravelMonorepoInit;
use Drmovi\MonorepoGenerator\Actions\Frameworks\LaravelPackageCreate;
use Drmovi\MonorepoGenerator\Actions\Frameworks\LaravelPackageDelete;
use Drmovi\MonorepoGenerator\Contracts\Operation;
use Drmovi\MonorepoGenerator\Dtos\ActionDto;
use Drmovi\MonorepoGenerator\Dtos\PackageDto;

class FrameworkOperationFactory
{
    private array $packages = [
        'laravel' => [
            'monorepo:init' => LaravelMonorepoInit::class,
            'monorepo:package:create' => LaravelPackageCreate::class,
            'monorepo:package:delete' => LaravelPackageDelete::class,
        ],
    ];

    public function make(ActionDto $dto): Operation
    {
        $class = $this->packages[$dto->input->getArgument('framework')][$dto->command->getName()] ?? null;
        if (!$class) {
            throw new \Exception("framework operation ({$dto->command->getName()}) for framework ({$dto->input->getArgument('framework')}) not found");
        }
        return new $class($dto);
    }
}
