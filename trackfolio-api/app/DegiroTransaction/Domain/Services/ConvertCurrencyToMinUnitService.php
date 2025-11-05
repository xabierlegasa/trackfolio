<?php

namespace App\DegiroTransaction\Domain\Services;

class ConvertCurrencyToMinUnitService
{
    /**
     * Convert currency value to smallest unit (cents).
     * 
     * Converts European format (comma as decimal separator) to integer cents.
     * Examples:
     * - "153,2600" -> 15326
     * - "-1072,82" -> -107282
     * - "147,6800" -> 14768
     * - "-125,73" -> -12573
     *
     * @param string|null $value The currency value in European format (e.g., "153,2600" or "-1072,82")
     * @return int|null The value in smallest currency unit (cents), or null if input is null/empty
     */
    public function convertToCents(?string $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        
        $cleaned = trim(trim($value, '"'));
        if ($cleaned === '' || $cleaned === null) {
            return null;
        }
        
        // Replace comma with dot (European format to standard format)
        $standard = str_replace(',', '.', $cleaned);
        
        // Parse as float
        $floatValue = (float) $standard;
        
        // Convert to cents (multiply by 100)
        return (int) round($floatValue * 100);
    }

    /**
     * Convert currency value to ten-thousandths of a unit.
     * 
     * Converts European format (comma as decimal separator) to integer ten-thousandths.
     * Examples:
     * - "153,2600" -> 1532600 (153.26 * 10000)
     * - "-1072,82" -> -10728200 (-1072.82 * 10000)
     * - "147,6800" -> 1476800 (147.68 * 10000)
     *
     * @param string|null $value The currency value in European format (e.g., "153,2600" or "-1072,82")
     * @return int|null The value in ten-thousandths, or null if input is null/empty
     */
    public function convertToTenThousandths(?string $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        
        $cleaned = trim(trim($value, '"'));
        if ($cleaned === '' || $cleaned === null) {
            return null;
        }
        
        // Replace comma with dot (European format to standard format)
        $standard = str_replace(',', '.', $cleaned);
        
        // Parse as float
        $floatValue = (float) $standard;
        
        // Convert to ten-thousandths (multiply by 10000)
        return (int) round($floatValue * 10000);
    }
}

