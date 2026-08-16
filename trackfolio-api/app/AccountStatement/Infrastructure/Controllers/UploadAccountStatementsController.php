<?php

namespace App\AccountStatement\Infrastructure\Controllers;

use App\AccountStatement\Application\UseCase\UploadAccountStatementsUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UploadAccountStatementsController
{
    public function __construct(
        private UploadAccountStatementsUseCase $uploadUseCase,
    ) {}

    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:20480',
        ]);

        $result = $this->uploadUseCase->execute(
            $request->file('file'),
            (int) Auth::id(),
        );

        if ($result->success) {
            return response()->json([
                'message' => $result->message,
                'count' => $result->count,
                'new_count' => $result->newCount,
                'ignored_count' => $result->ignoredCount,
            ], 201);
        }

        return response()->json([
            'message' => $result->message,
            'errors' => $result->errors ?? [],
        ], 422);
    }
}
