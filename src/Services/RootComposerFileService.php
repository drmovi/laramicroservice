<?php

namespace Drmovi\MonorepoGenerator\Services;

class RootComposerFileService extends ComposerFileService
{

    public function getMonorepoData(): ?array
    {
        $data = $this->getContent();
        return $data['extra']['monorepo'] ?? null;
    }


    public function addMonoRepoConfigs(array $data)
    {
        foreach ($data as $key => $value) {
            $this->composer->runComposerCommand([
                'config',
                '--working-dir',
                $this->path,
                "extra.monorepo.$key",
                $value
            ]);
        }
    }
}
