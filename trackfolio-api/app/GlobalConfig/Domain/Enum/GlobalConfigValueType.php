<?php

namespace App\GlobalConfig\Domain\Enum;

enum GlobalConfigValueType: string
{
    case String = 'string';
    case Integer = 'integer';
    case Bool = 'bool';
}
