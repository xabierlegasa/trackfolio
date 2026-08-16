<?php

namespace App\Portfolio\Infrastructure\Controllers;

use App\Portfolio\Application\UseCase\StartRecalculateEvolutionUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StartRecalculateEvolutionController
{
    public function __construct(
        private StartRecalculateEvolutionUseCase $startRecalculateEvolutionUseCase,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $result = $this->startRecalculateEvolutionUseCase->execute((int) $request->user()->id);

        return response()->json([
            'message' => 'Evolution recalculation started.',
            'process_id' => $result['process_id'],
            'started_from' => $result['started_from'],
            'deleted' => $result['deleted'],
        ], 202);
    }
}
