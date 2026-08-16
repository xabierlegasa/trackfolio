<?php

namespace App\GlobalConfig\Domain\Service;

use App\GlobalConfig\Domain\Entity\GlobalConfig;
use App\GlobalConfig\Infrastructure\Repository\GlobalConfigRepository;

class GetGlobalConfigService
{
    public const RECALCULATE_EVOLUTION_FEATURE = 'is_recalculate_evolution_feature_enabled';

    public function __construct(
        private GlobalConfigRepository $globalConfigRepository,
    ) {}

    public function isRecalculateEvolutionFeatureEnabled(): bool
    {
        $row = $this->globalConfigRepository->findByCode(self::RECALCULATE_EVOLUTION_FEATURE);
        if ($row === null) {
            return false;
        }

        return $this->asBool($row);
    }

    private function asBool(GlobalConfig $row): bool
    {
        $value = strtolower(trim((string) $row->value));

        return in_array($value, ['1', 'true', 'yes'], true);
    }
}
