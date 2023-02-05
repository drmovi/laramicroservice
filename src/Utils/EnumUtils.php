<?php

namespace Drmovi\MonorepoGenerator\Utils;

trait EnumUtils
{
    public static  function valuesToArray():array
    {
        return array_map(fn($item) => $item->value, self::cases());
    }
}
