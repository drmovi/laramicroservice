<?php

namespace Drmovi\MonorepoGenerator\Validators;

interface Validator
{

    public function validate(mixed $value): bool;

    public function getErrorMessage(): ?string;
}
