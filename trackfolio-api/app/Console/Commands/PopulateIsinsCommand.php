<?php

namespace App\Console\Commands;

use App\DegiroTransaction\Domain\Entity\DegiroTransaction;
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
    protected $signature = 'isins:populate {--provider= : Provider to use (eodhd, finnhub, fmp, or alphavantage). Defaults to eodhd}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Populate isins table with information from degiro_transactions using stock API';

    /**
     * Execute the console command.
     */
    public function handle(StockApiService $stockApiService): int
    {
        $provider = $this->option('provider') ?? StockApiService::PROVIDER_EODHD;

        // Validate provider
        $allowedProviders = [
            StockApiService::PROVIDER_EODHD,
            StockApiService::PROVIDER_FINNHUB,
            StockApiService::PROVIDER_FMP,
            StockApiService::PROVIDER_ALPHAVANTAGE,
        ];
        if (!in_array($provider, $allowedProviders, true)) {
            $this->error('Invalid provider: ' . $provider . '. Available: ' . implode(', ', $allowedProviders));
            return Command::FAILURE;
        }

        $this->info("Starting to populate ISINs using provider: {$provider}");

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

                Log::info("🔍 Requesting stock info for ISIN", [
                    'isin' => $isin,
                    'provider' => $provider,
                ]);

                $stockInfo = $stockApiService->getStockInfo($isin, $provider);

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
