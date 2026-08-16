<?php

namespace App\Isin\Domain\Service;

use App\Isin\Domain\Entity\ProviderRequest;

class RecordProviderRequestService
{
    public function record(
        string $provider,
        string $callType,
        string $method,
        string $url,
        ?int $httpStatus,
        ?string $responseBody,
        ?int $durationMs,
        bool $success,
        ?string $errorMessage = null,
    ): ProviderRequest {
        return ProviderRequest::query()->create([
            'provider' => strtolower($provider),
            'call_type' => $callType,
            'method' => strtoupper($method),
            'url' => $url,
            'http_status' => $httpStatus,
            'response_body' => $this->truncateBody($responseBody),
            'duration_ms' => $durationMs,
            'success' => $success,
            'error_message' => $errorMessage,
        ]);
    }

    private function truncateBody(?string $body): ?string
    {
        if ($body === null) {
            return null;
        }

        // Keep enough for debugging without blowing up the row size.
        $maxBytes = 65535;
        if (strlen($body) <= $maxBytes) {
            return $body;
        }

        return substr($body, 0, $maxBytes) . '…[truncated]';
    }
}
