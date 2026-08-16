<?php

namespace App\GlobalConfig\Domain\Entity;

use App\GlobalConfig\Domain\Enum\GlobalConfigValueType;
use Illuminate\Database\Eloquent\Model;

class GlobalConfig extends Model
{
    protected $table = 'global_config';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'code',
        'value',
        'value_type',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'value_type' => GlobalConfigValueType::class,
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
