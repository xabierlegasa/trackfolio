<?php

namespace App\Admin\Infrastructure\Controllers;

use App\Isin\Domain\Entity\ProviderRequest;
use Illuminate\Http\JsonResponse;

class ShowProviderRequestController
{
    public function show(int $providerRequestId): JsonResponse
    {
        $row = ProviderRequest::query()->find($providerRequestId);
        if ($row === null) {
            return response()->json(['message' => 'Provider request not found'], 404);
        }

        return response()->json([
            'data' => [
                'id' => $row->id,
                'provider' => $row->provider,
                'call_type' => $row->call_type,
                'method' => $row->method,
                'url' => $row->url,
                'http_status' => $row->http_status,
                'response_body' => $row->response_body,
                'duration_ms' => $row->duration_ms,
                'success' => $row->success,
                'error_message' => $row->error_message,
                'created_at' => $row->created_at?->toIso8601String(),
            ],
        ]);
    }
}
