<?php

namespace Drmovi\MonorepoGenerator\Services;

class RootComposerFileService extends ComposerFileService
{

    public function getMonorepoData(): ?array
    {
        if (!$this->canOperate()) {
            return null;
        }
        $data = $this->getContent();
        return $data['extra']['monorepo'] ?? null;
    }


    public function addMonoRepoConfigs(array $data): void
    {
        if (!$this->canOperate()) {
            return;
        }
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
