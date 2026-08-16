<?php

namespace App\AccountStatement\Infrastructure\Repository;

use App\AccountStatement\Domain\Entity\AccountStatement;

class AccountStatementRepository
{
    private const HASH_LOOKUP_CHUNK_SIZE = 1000;

    private const INSERT_CHUNK_SIZE = 500;

    /**
     * @param  list<string>  $hashes
     * @return list<string>
     */
    public function findExistingContentHashes(int $userId, array $hashes): array
    {
        if ($hashes === []) {
            return [];
        }

        $existing = [];
        foreach (array_chunk($hashes, self::HASH_LOOKUP_CHUNK_SIZE) as $chunk) {
            $found = AccountStatement::query()
                ->where('user_id', $userId)
                ->whereIn('custom_content_hash', $chunk)
                ->pluck('custom_content_hash')
                ->all();
            array_push($existing, ...$found);
        }

        return $existing;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function createMany(array $rows): int
    {
        if ($rows === []) {
            return 0;
        }

        $inserted = 0;
        foreach (array_chunk($rows, self::INSERT_CHUNK_SIZE) as $chunk) {
            AccountStatement::insert($chunk);
            $inserted += count($chunk);
        }

        return $inserted;
    }

    /**
     * Latest EUR cash balance on or before $asOfDate (Y-m-d).
     */
    public function cashEurMinUnitOnOrBefore(int $userId, string $asOfDate): int
    {
        $row = AccountStatement::query()
            ->where('user_id', $userId)
            ->whereDate('date', '<=', $asOfDate)
            ->where('balance_currency', 'EUR')
            ->whereNotNull('balance_min_unit')
            ->orderByDesc('date')
            ->orderByDesc('time')
            ->orderByDesc('id')
            ->first(['balance_min_unit']);

        return $row !== null ? (int) $row->balance_min_unit : 0;
    }
}
