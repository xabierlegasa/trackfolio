<?php

namespace App\AccountStatement\Domain\Service;

use App\AccountStatement\Domain\DTO\AccountStatementDTO;
use App\AccountStatement\Domain\DTO\UploadAccountStatementsResult;
use App\AccountStatement\Infrastructure\Repository\AccountStatementRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UploadAccountStatementsService
{
    public function __construct(
        private AccountStatementRepository $repository,
        private ParseAccountStatementRowService $rowParser,
        private ReadAccountStatementsCsvService $csvReader,
    ) {}

    public function processCsv(UploadedFile $file, int $userId): UploadAccountStatementsResult
    {
        try {
            $read = $this->csvReader->read($file);
            if ($read['errors'] !== []) {
                return UploadAccountStatementsResult::failure(
                    'CSV validation failed',
                    $read['errors'],
                );
            }

            $parsed = [];
            $parseErrors = [];
            foreach ($read['rows'] as $entry) {
                $dto = $this->rowParser->parse($entry['values'], $userId);
                if ($dto === null) {
                    $parseErrors[] = "Line {$entry['line']}: Failed to parse row after validation (invalid or incomplete data)";
                    continue;
                }
                $parsed[] = $dto;
            }

            if ($parseErrors !== []) {
                return UploadAccountStatementsResult::failure(
                    'CSV validation failed',
                    $parseErrors,
                );
            }

            if ($parsed === []) {
                return UploadAccountStatementsResult::failure('No valid account statement rows found in CSV file');
            }

            $hashes = array_map(static fn (AccountStatementDTO $dto) => $dto->customContentHash, $parsed);
            $existing = array_flip($this->repository->findExistingContentHashes($userId, $hashes));

            $newRows = [];
            $ignoredCount = 0;
            foreach ($parsed as $dto) {
                if (isset($existing[$dto->customContentHash])) {
                    $ignoredCount++;
                    continue;
                }
                $newRows[] = $dto;
                $existing[$dto->customContentHash] = true;
            }

            if ($newRows === []) {
                return UploadAccountStatementsResult::success(
                    'All account statement rows were already in the database',
                    0,
                    $ignoredCount,
                );
            }

            DB::beginTransaction();
            try {
                $now = now();
                $arrays = array_map(static function (AccountStatementDTO $dto) use ($now) {
                    $row = $dto->toArray();
                    $row['created_at'] = $now;
                    $row['updated_at'] = $now;

                    return $row;
                }, $newRows);

                $newCount = $this->repository->createMany($arrays);
                DB::commit();

                return UploadAccountStatementsResult::success(
                    "{$newCount} account statement rows uploaded successfully",
                    $newCount,
                    $ignoredCount,
                );
            } catch (\Throwable $e) {
                DB::rollBack();
                Log::error('Failed to store account statements: ' . $e->getMessage());

                return UploadAccountStatementsResult::failure('Failed to store account statements: ' . $e->getMessage());
            }
        } catch (\Throwable $e) {
            Log::error('Error processing account statement CSV: ' . $e->getMessage());

            return UploadAccountStatementsResult::failure('Error processing CSV file: ' . $e->getMessage());
        }
    }
}
