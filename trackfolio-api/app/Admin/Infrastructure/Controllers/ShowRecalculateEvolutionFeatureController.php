<?php

namespace App\Admin\Infrastructure\Controllers;

use App\GlobalConfig\Domain\Service\GetGlobalConfigService;
use Illuminate\Http\JsonResponse;

class ShowRecalculateEvolutionFeatureController
{
    public function __construct(
        private GetGlobalConfigService $getGlobalConfigService,
    ) {}

    public function show(): JsonResponse
    {
        return response()->json([
            'code' => GetGlobalConfigService::RECALCULATE_EVOLUTION_FEATURE,
            'enabled' => $this->getGlobalConfigService->isRecalculateEvolutionFeatureEnabled(),
        ]);
    }
}
