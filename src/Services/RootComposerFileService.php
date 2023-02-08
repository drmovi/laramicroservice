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
                dirname($this->path),
                "extra.monorepo.$key",
                $value
            ]);
        }
    }

    public function addExtra(array $data)
    {
        if (!$this->canOperate()) {
            return;
        }
        foreach ($data as $key => $value) {
            $data = [
                'config',
                '--working-dir',
                dirname($this->path)
            ];
            if (is_array($value)) {
                $data[] = '--json';
                $value = json_encode($value);
            }
            $data[] = "extra.$key";
            $data[] = $value;
            $this->composer->runComposerCommand($data);
        }
    }
}
