<?php

namespace App\Console\Commands;

use App\DegiroTransaction\Domain\Entity\DegiroTransaction;
use App\Isin\Domain\DTO\StockInfoDTO;
use App\Isin\Domain\Entity\Isin;
use App\Isin\Domain\Service\StockApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PopulateIsinsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'isins:populate {--provider= : Provider to use (finnhub, fmp, or alphavantage). Defaults to finnhub}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Populate isins table with information from degiro_transactions using stock API';

    /**
     * Path to the manual ISIN data JSON file.
     */
    private const MANUAL_DATA_PATH = 'database/data/isin_manual_data.json';

    /**
     * Load manual ISIN data from JSON file.
     *
     * @return array<string, array{isin: string, symbol: string, description: string, type: string, display_symbol: string}>
     */
    private function loadManualData(): array
    {
        $filePath = base_path(self::MANUAL_DATA_PATH);

        if (!file_exists($filePath)) {
            return [];
        }

        $content = file_get_contents($filePath);
        $data = json_decode($content, true);

        if (!is_array($data)) {
            return [];
        }

        // Convert array to associative array keyed by ISIN
        $manualData = [];
        foreach ($data as $item) {
            if (isset($item['isin'])) {
                $manualData[$item['isin']] = $item;
            }
        }

        return $manualData;
    }

    /**
     * Get stock info from manual data if available.
     *
     * @param string $isin
     * @param array $manualData
     * @return StockInfoDTO|null
     */
    private function getStockInfoFromManualData(string $isin, array $manualData): ?StockInfoDTO
    {
        if (!isset($manualData[$isin])) {
            return null;
        }

        $data = $manualData[$isin];

        return new StockInfoDTO(
            symbol: $data['symbol'] ?? '',
            description: $data['description'] ?? null,
            displaySymbol: $data['display_symbol'] ?? $data['symbol'] ?? null,
            type: $data['type'] ?? null,
            quote: null, // Manual data doesn't include quote
        );
    }

    /**
     * Execute the console command.
     */
    public function handle(StockApiService $stockApiService): int
    {
        $provider = $this->option('provider') ?? StockApiService::PROVIDER_FINNHUB;

        // Validate provider
        if (!in_array($provider, [StockApiService::PROVIDER_FINNHUB, StockApiService::PROVIDER_FMP, StockApiService::PROVIDER_ALPHAVANTAGE])) {
            $this->error("Invalid provider: {$provider}. Available: finnhub, fmp, alphavantage");
            return Command::FAILURE;
        }

        $this->info("Starting to populate ISINs using provider: {$provider}");

        // Load manual ISIN data
        $manualData = $this->loadManualData();
        $manualDataCount = count($manualData);
        if ($manualDataCount > 0) {
            $this->info("Loaded {$manualDataCount} ISIN(s) from manual data file");
        }

        // Get all unique ISINs from degiro_transactions
        $uniqueIsins = DegiroTransaction::select('isin')
            ->distinct()
            ->whereNotNull('isin')
            ->where('isin', '!=', '')
            ->pluck('isin')
            ->toArray();

        $this->info("Found " . count($uniqueIsins) . " unique ISINs in degiro_transactions");

        // Get ISINs that already exist in isins table
        $existingIsins = Isin::pluck('isin')->toArray();
        $existingIsinsSet = array_flip($existingIsins);

        // Filter out ISINs that already exist
        $isinsToProcess = array_filter($uniqueIsins, function ($isin) use ($existingIsinsSet) {
            return !isset($existingIsinsSet[$isin]);
        });

        $this->info("Found " . count($isinsToProcess) . " ISINs to process (excluding " . count($existingIsins) . " already in database)");

        if (empty($isinsToProcess)) {
            $this->info("No new ISINs to process. All ISINs are already in the database.");
            return Command::SUCCESS;
        }

        $progressBar = $this->output->createProgressBar(count($isinsToProcess));
        $progressBar->start();

        $successCount = 0;
        $errorCount = 0;
        $errors = [];

        foreach ($isinsToProcess as $isin) {
            try {
                Log::info("--------------------------------");
                
                // Check manual data first
                $stockInfo = $this->getStockInfoFromManualData($isin, $manualData);
                
                if ($stockInfo) {
                    Log::info("📝 Using manual data for ISIN", [
                        'isin' => $isin,
                        'data' => $stockInfo->toArray(),
                    ]);
                } else {
                    // Log request
                    Log::info("🔍 Requesting stock info for ISIN", [
                        'isin' => $isin,
                        'provider' => $provider,
                    ]);

                    // Get stock info from API
                    $stockInfo = $stockApiService->getStockInfo($isin, $provider);

                    // Log response
                    if ($stockInfo) {
                        Log::info("✅ Received stock info response", [
                            'isin' => $isin,
                            'provider' => $provider,
                            'response' => $stockInfo->toArray(),
                        ]);
                    } else {
                        Log::info("❌ No stock info found in API", [
                            'isin' => $isin,
                            'provider' => $provider,
                        ]);
                    }
                }

                if (!$stockInfo) {
                    $errorCount++;
                    $errors[] = "ISIN {$isin}: No information found in API";
                    $progressBar->advance();
                    continue;
                }

                // Validate required fields
                if (empty($stockInfo->symbol) || empty($stockInfo->description)) {
                    Log::warning("❌❌ Missing required fields in response", [
                        'isin' => $isin,
                        'provider' => $provider,
                        'response' => $stockInfo->toArray(),
                    ]);
                    $errorCount++;
                    $errors[] = "ISIN {$isin}: Missing required fields (symbol or description)";
                    $progressBar->advance();
                    continue;
                }

                // Create or update ISIN record
                Isin::updateOrCreate(
                    ['isin' => $isin],
                    [
                        'symbol' => $stockInfo->symbol,
                        'description' => $stockInfo->description ?? '',
                        'type' => $stockInfo->type ?? 'stock',
                        'display_symbol' => $stockInfo->displaySymbol ?? $stockInfo->symbol,
                    ]
                );

                Log::info("✅ Successfully saved ISIN to database", [
                    'isin' => $isin,
                    'symbol' => $stockInfo->symbol,
                ]);

                $successCount++;
            } catch (\Exception $e) {
                Log::error("❌ Error processing ISIN", [
                    'isin' => $isin,
                    'provider' => $provider,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                $errorCount++;
                $errors[] = "ISIN {$isin}: " . $e->getMessage();
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        // Display results
        $this->info("Processing completed!");
        $this->info("Successfully processed: {$successCount} ISINs");
        $this->info("Errors: {$errorCount} ISINs");

        // Display errors if any
        if (!empty($errors)) {
            $this->newLine();
            $this->warn("Errors encountered:");
            foreach ($errors as $error) {
                $this->line("  - {$error}");
            }
        }

        return Command::SUCCESS;
    }
}
