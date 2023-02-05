<?php

namespace Drmovi\MonorepoGenerator\Contracts;

interface Operation extends State
{
    public function exec(): int;

}
