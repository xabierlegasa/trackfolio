<?php

namespace App\DegiroTransaction\Infrastructure\Controllers;

use App\Isin\Domain\Service\ResolveLastUsMarketOpenDateService;
use App\Portfolio\Domain\Service\GetPortfolioAsOfViewService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PortfolioStatsController
{
    private const SORTABLE = [
        'symbols' => 'ticker_symbol',
        'price' => 'closing_price_min_unit',
        'quantity' => 'quantity',
        'total' => 'market_value_min_unit',
        'total_eur' => 'market_value_eur_min_unit',
        'change' => 'day_change_min_unit',
        'total_gain_loss' => 'total_gain_loss_min_unit',
        'total_gain_loss_eur' => 'total_gain_loss_eur_min_unit',
        'weight' => 'weight_percent',
    ];

    public function __construct(
        private ResolveLastUsMarketOpenDateService $resolveLastUsMarketOpenDateService,
        private GetPortfolioAsOfViewService $getPortfolioAsOfViewService,
    ) {}

    /**
     * Paginated portfolio holdings as of a US market-open date.
     * The full view is cached per user+date (Laravel cache + snapshot payload).
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $userId = (int) $user->id;
        $perPage = max(1, min(100, (int) $request->get('per_page', 10)));
        $page = max(1, (int) $request->get('page', 1));

        $sortByParam = (string) $request->get('sort_by', 'weight');
        $sortBy = self::SORTABLE[$sortByParam] ?? 'weight_percent';
        $sortOrder = strtolower((string) $request->get('sort_order', 'desc')) === 'asc' ? 'asc' : 'desc';

        $lastUsMarketOpenDate = $this->resolveLastUsMarketOpenDateService->resolve()
            ?? Carbon::yesterday('America/New_York')->toDateString();

        $asOfDate = $this->requestedAsOfDate($request, $lastUsMarketOpenDate);
        if ($asOfDate instanceof JsonResponse) {
            return $asOfDate;
        }

        $payload = $this->getPortfolioAsOfViewService->get($userId, $asOfDate);

        return $this->jsonFromPayload(
            $payload,
            $lastUsMarketOpenDate,
            $page,
            $perPage,
            $sortBy,
            $sortByParam,
            $sortOrder,
        );
    }

    /**
     * @return string|JsonResponse
     */
    private function requestedAsOfDate(Request $request, string $lastUsMarketOpenDate): string|JsonResponse
    {
        $raw = $request->query('as_of');
        if ($raw === null || $raw === '') {
            return $lastUsMarketOpenDate;
        }

        $asOf = (string) $raw;
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $asOf)) {
            return $this->marketClosedResponse(
                'Invalid as-of date. Use YYYY-MM-DD.',
                $asOf,
                'invalid',
            );
        }

        try {
            $parsed = Carbon::createFromFormat('Y-m-d', $asOf);
            if ($parsed === false || $parsed->toDateString() !== $asOf) {
                return $this->marketClosedResponse('Invalid as-of date.', $asOf, 'invalid');
            }
        } catch (\Throwable) {
            return $this->marketClosedResponse('Invalid as-of date.', $asOf, 'invalid');
        }

        if ($asOf > $lastUsMarketOpenDate) {
            return $this->marketClosedResponse(
                "Closing prices for {$asOf} are not available yet. The latest completed US session is {$lastUsMarketOpenDate}.",
                $asOf,
                'too_recent',
            );
        }

        $status = $this->resolveLastUsMarketOpenDateService->marketStatusOn($asOf);
        if (!$status['open']) {
            $message = $this->closedMarketMessage($asOf, $status['reason'], $status['holiday']);

            return $this->marketClosedResponse($message, $asOf, $status['reason'] ?? 'closed', $status['holiday']);
        }

        return $asOf;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function jsonFromPayload(
        array $payload,
        string $lastUsMarketOpenDate,
        int $page,
        int $perPage,
        string $sortBy,
        string $sortByParam,
        string $sortOrder,
    ): JsonResponse {
        $holdings = is_array($payload['holdings'] ?? null) ? $payload['holdings'] : [];
        $holdings = $this->sortHoldings($holdings, $sortBy, $sortOrder);

        $total = count($holdings);
        $lastPage = max(1, (int) ceil($total / $perPage));
        if ($page > $lastPage) {
            $page = $lastPage;
        }
        $pageItems = array_slice($holdings, ($page - 1) * $perPage, $perPage);

        return response()->json([
            'data' => array_values($pageItems),
            'concentration' => $payload['concentration'] ?? [],
            'performance_temperature' => $payload['performance_temperature'] ?? [],
            'current_page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => $lastPage,
            'sort_by' => $sortByParam,
            'sort_order' => $sortOrder,
            'usd_to_eur_rate' => $payload['usd_to_eur_rate'] ?? null,
            'usd_to_eur_rate_date' => $payload['usd_to_eur_rate_date'] ?? null,
            'as_of_date' => $payload['as_of_date'] ?? null,
            'last_us_market_open_date' => $lastUsMarketOpenDate,
            'total_market_value_min_unit' => $payload['total_market_value_min_unit'] ?? null,
            'total_market_value_eur_min_unit' => $payload['total_market_value_eur_min_unit'] ?? null,
            'cash_eur_min_unit' => $payload['cash_eur_min_unit'] ?? 0,
            'net_market_value_eur_min_unit' => $payload['net_market_value_eur_min_unit'] ?? null,
            'day_change_eur_min_unit' => $payload['day_change_eur_min_unit'] ?? null,
            'total_gain_loss_eur_min_unit' => $payload['total_gain_loss_eur_min_unit'] ?? null,
        ]);
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function sortHoldings(array $rows, string $sortBy, string $sortOrder): array
    {
        $dir = $sortOrder === 'asc' ? 1 : -1;

        usort($rows, function (array $a, array $b) use ($sortBy, $dir) {
            if ($sortBy === 'ticker_symbol') {
                $va = strtoupper((string) ($a['ticker_symbol'] ?? $a['product'] ?? ''));
                $vb = strtoupper((string) ($b['ticker_symbol'] ?? $b['product'] ?? ''));
                $cmp = $va <=> $vb;
                if ($cmp === 0) {
                    $cmp = strcasecmp((string) ($a['product'] ?? ''), (string) ($b['product'] ?? ''));
                }

                return $dir * $cmp;
            }

            $va = $a[$sortBy] ?? null;
            $vb = $b[$sortBy] ?? null;

            if ($va === null && $vb === null) {
                return 0;
            }
            if ($va === null) {
                return 1;
            }
            if ($vb === null) {
                return -1;
            }

            return $dir * ($va <=> $vb);
        });

        return $rows;
    }

    private function closedMarketMessage(string $date, ?string $reason, ?string $holiday): string
    {
        return match ($reason) {
            'weekend' => "The US stock market was closed on {$date} (weekend).",
            'holiday' => $holiday !== null && $holiday !== ''
                ? "The US stock market was closed on {$date} ({$holiday})."
                : "The US stock market was closed on {$date} (holiday).",
            default => "The US stock market was closed on {$date}.",
        };
    }

    private function marketClosedResponse(
        string $message,
        string $date,
        string $reason,
        ?string $holiday = null,
    ): JsonResponse {
        return response()->json([
            'message' => $message,
            'error' => 'market_closed',
            'date' => $date,
            'reason' => $reason,
            'holiday' => $holiday,
        ], 400);
    }
}
