<?php

namespace App\Admin\Infrastructure\Controllers;

use App\GlobalConfig\Domain\Service\GetGlobalConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

class UpdateRecalculateEvolutionFeatureController
{
    public function __construct(
        private GetGlobalConfigService $getGlobalConfigService,
    ) {}

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);

        try {
            $enabled = $this->getGlobalConfigService->setRecalculateEvolutionFeatureEnabled(
                (bool) $validated['enabled'],
            );
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 404);
        } catch (Throwable $exception) {
            return response()->json(['message' => $exception->getMessage()], 500);
        }

        return response()->json([
            'code' => GetGlobalConfigService::RECALCULATE_EVOLUTION_FEATURE,
            'enabled' => $enabled,
        ]);
    }
}
