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

    public function updateValueByCode(string $code, string $value): ?GlobalConfig
    {
        $row = $this->findByCode($code);
        if ($row === null) {
            return null;
        }

        $row->value = $value;
        $row->save();

        return $row;
    }
}
