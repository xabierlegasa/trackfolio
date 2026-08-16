<?php

namespace App\GlobalConfig\Infrastructure\Repository;

use App\GlobalConfig\Domain\Entity\GlobalConfig;

class GlobalConfigRepository
{
    public function findByCode(string $code): ?GlobalConfig
    {
        return GlobalConfig::query()
            ->where('code', $code)
            ->first();
    }
}
