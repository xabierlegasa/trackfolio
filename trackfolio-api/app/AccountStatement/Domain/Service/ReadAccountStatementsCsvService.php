<?php

namespace App\AccountStatement\Domain\Service;

use Illuminate\Http\UploadedFile;

/**
 * Reads Degiro Account Statement CSV into logical rows for validation/upload.
 *
 * Degiro sometimes inserts a raw newline inside Description without quoting,
 * producing a follow-up row with only Description. Those rows are ignored.
 */
class ReadAccountStatementsCsvService
{
    /**
     * @return array{
     *     header: list<string|null>|null,
     *     rows: list<array{line: int, values: list<string|null>}>,
     *     errors: list<string>
     * }
     */
    public function read(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'r');
        if ($handle === false) {
            return [
                'header' => null,
                'rows' => [],
                'errors' => ['Unable to open CSV file'],
            ];
        }

        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);

            return [
                'header' => null,
                'rows' => [],
                'errors' => ['CSV file is empty or invalid'],
            ];
        }

        $errors = $this->validateHeader($header);
        if ($errors !== []) {
            fclose($handle);

            return [
                'header' => $header,
                'rows' => [],
                'errors' => $errors,
            ];
        }

        $rows = [];
        $lineNumber = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $lineNumber++;
            if ($this->isBlankRow($row) || $this->isDescriptionOnlyRow($row)) {
                continue;
            }

            if ($this->clean($row[0] ?? null) === null) {
                $errors = array_merge($errors, $this->describeInvalidRowWithoutDate($row, $lineNumber));
                continue;
            }

            $rows[] = ['line' => $lineNumber, 'values' => $row];
        }

        fclose($handle);

        return [
            'header' => $header,
            'rows' => $rows,
            'errors' => $errors,
        ];
    }

    /**
     * @param  list<string|null>  $header
     * @return list<string>
     */
    private function validateHeader(array $header): array
    {
        $errors = [];
        if (count($header) < 11) {
            $errors[] = 'Invalid Account Statement header: expected Degiro Account CSV columns (Date … Balance … Order Id)';
        }

        $first = strtolower($this->clean($header[0] ?? null) ?? '');
        $balance = strtolower($this->clean($header[9] ?? null) ?? '');
        if ($first !== 'date' || $balance !== 'balance') {
            $errors[] = 'CSV must be a Degiro Account Statement export (Date … Balance … Order Id)';
        }

        return $errors;
    }

    /**
     * @param  list<string|null>  $row
     * @return list<string>
     */
    private function describeInvalidRowWithoutDate(array $row, int $lineNumber): array
    {
        $errors = ["Line {$lineNumber}, column 1 (Date): Date is required (DD-MM-YYYY)."];

        $nonEmpty = [];
        foreach ($row as $index => $value) {
            $cleaned = $this->clean($value);
            if ($cleaned !== null) {
                $nonEmpty[] = 'column ' . ($index + 1) . "='{$cleaned}'";
            }
        }
        if ($nonEmpty !== []) {
            $errors[] = "Line {$lineNumber}: row has no Date but contains: " . implode(', ', $nonEmpty) . '.';
        }

        return $errors;
    }

    /**
     * @param  list<string|null>  $row
     */
    private function isBlankRow(array $row): bool
    {
        return $row === []
            || empty(array_filter($row, static fn ($v) => $v !== null && trim((string) $v) !== ''));
    }

    /**
     * Rows with no Date and only Description filled (Degiro unquoted newline).
     *
     * @param  list<string|null>  $row
     */
    private function isDescriptionOnlyRow(array $row): bool
    {
        if ($this->clean($row[0] ?? null) !== null) {
            return false;
        }

        if ($this->clean($row[5] ?? null) === null) {
            return false;
        }

        foreach ([1, 2, 3, 4, 6, 7, 8, 9, 10, 11] as $index) {
            if ($this->clean($row[$index] ?? null) !== null) {
                return false;
            }
        }

        return true;
    }

    private function clean(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $trimmed = trim(trim((string) $value, '"'));

        return $trimmed === '' ? null : $trimmed;
    }
}
