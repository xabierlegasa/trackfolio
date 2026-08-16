<?php

namespace App\Isin\Domain\Service;

use App\Isin\Domain\DTO\ProviderCandleCallResult;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ThrottleStockApiRequestService
{
    private const MIN_INTERVAL_MS = 1000;

    private const RETRY_BACKOFF_MS = 2000;

    /**
     * @param  callable(): ProviderCandleCallResult  $call
     */
    public function execute(string $provider, string $symbol, callable $call): ProviderCandleCallResult
    {
        $provider = strtolower($provider);

        if ($this->isInCooldown($provider)) {
            return new ProviderCandleCallResult(
                success: false,
                candle: null,
                response: null,
                httpStatus: null,
                errorMessage: "Provider {$provider} is in rate-limit cooldown",
                rateLimited: true,
            );
        }

        $this->waitTurn($provider);
        $result = $call();

        if (!$this->isRateLimitedResult($result)) {
            return $result;
        }

        Log::warning("stock_api.rate_limited provider={$provider} symbol={$symbol} action=retry_backoff");
        usleep(self::RETRY_BACKOFF_MS * 1000);

        if ($this->isInCooldown($provider)) {
            return $result;
        }

        $this->waitTurn($provider);
        $result = $call();

        if ($this->isRateLimitedResult($result)) {
            $this->markRateLimited($provider);
            Log::warning("stock_api.rate_limited provider={$provider} symbol={$symbol} action=cooldown_until_utc_midnight");
        }

        return $result;
    }

    public function isInCooldown(string $provider): bool
    {
        return Cache::has($this->cooldownKey(strtolower($provider)));
    }

    public function waitTurn(string $provider): void
    {
        $provider = strtolower($provider);
        $key = $this->lastRequestKey($provider);
        $nowMs = (int) floor(microtime(true) * 1000);
        $lastMs = (int) (Cache::get($key) ?? 0);
        $elapsed = $nowMs - $lastMs;
        if ($lastMs > 0 && $elapsed < self::MIN_INTERVAL_MS) {
            usleep((self::MIN_INTERVAL_MS - $elapsed) * 1000);
        }
        Cache::put($key, (int) floor(microtime(true) * 1000), 120);
    }

    public function markRateLimited(string $provider): void
    {
        $provider = strtolower($provider);
        $seconds = max(60, $this->secondsUntilUtcMidnight());
        Cache::put($this->cooldownKey($provider), true, $seconds);
    }

    public function isRateLimitedResult(ProviderCandleCallResult $result): bool
    {
        if ($result->rateLimited || $result->httpStatus === 429) {
            return true;
        }

        $haystack = strtolower(($result->errorMessage ?? '') . ' ' . json_encode($result->response));

        return str_contains($haystack, 'limit reach')
            || str_contains($haystack, 'too many requests')
            || str_contains($haystack, 'rate limit')
            || str_contains($haystack, '25 requests per day')
            || str_contains($haystack, 'premium plans')
            || (str_contains($haystack, 'thank you for using alpha vantage') && str_contains($haystack, 'call frequency'));
    }

    public static function redactSecretsInUrl(string $url): string
    {
        $redacted = preg_replace('/([?&](?:apikey|token|api_key)=)[^&]*/i', '$1***', $url);

        return is_string($redacted) ? $redacted : $url;
    }

    private function secondsUntilUtcMidnight(): int
    {
        $now = time();
        $midnight = strtotime('tomorrow', $now);

        return max(60, $midnight - $now);
    }

    private function lastRequestKey(string $provider): string
    {
        return "stock_api:last_request_ms:{$provider}";
    }

    private function cooldownKey(string $provider): string
    {
        return "stock_api:cooldown:{$provider}";
    }
}
