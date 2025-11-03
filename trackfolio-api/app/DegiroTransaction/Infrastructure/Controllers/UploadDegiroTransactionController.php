<?php

namespace App\DegiroTransaction\Infrastructure\Controllers;

use App\DegiroTransaction\Application\UseCase\UploadDegiroTransactionsUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UploadDegiroTransactionController
{
    public function __construct(
        private UploadDegiroTransactionsUseCase $uploadUseCase
    ) {}

    /**
     * Upload Degiro transactions CSV file.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:10240', // 10MB max
        ]);

        $file = $request->file('file');
        $userId = Auth::id();

        $result = $this->uploadUseCase->execute($file, $userId);

        if ($result['success']) {
            return response()->json([
                'message' => $result['message'],
                'count' => $result['count']
            ], 201);
        }

        $response = [
            'message' => $result['message'],
            'error' => 'Upload failed'
        ];

        // Include validation errors if present
        if (isset($result['errors']) && !empty($result['errors'])) {
            $response['errors'] = $result['errors'];
        }

        return response()->json($response, 422);
    }
}

