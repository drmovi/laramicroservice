<?php

namespace Drmovi\MonorepoGenerator\Validators;

class PackageNameValidator implements Validator
{
    private string $regex = '/^[a-z_]+$/';
    private mixed $value = null;

    public function validate(mixed $value): bool
    {
        $this->value = $value;
        return preg_match($this->regex, $value) === 1;
    }

    public function getErrorMessage(): string
    {
        return "Invalid package name: {$this->value}. It should match the following regex: {$this->regex}";
    }
}
