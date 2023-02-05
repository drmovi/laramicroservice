<?php

namespace Drmovi\MonorepoGenerator\Validators;

class VendorNameValidator implements Validator
{

    private string $regex = '/^[a-z_]+$/';

    public function validate(mixed $value): bool
    {
        return preg_match($this->regex, $value) === 1;
    }

    public function getErrorMessage(): string
    {
        return "Invalid vendor name. It should match the following regex: {$this->regex}";
    }
}
