<?php

namespace App\Services\Documents\Extraction;

use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class ExcelExtractor implements DocumentExtractorInterface
{
    public function supports(string $mimeType, string $extension): bool
    {
        $ext = strtolower($extension);
        return in_array($ext, ['xlsx', 'xls', 'csv']) || str_contains($mimeType, 'spreadsheet') || str_contains($mimeType, 'excel');
    }

    public function extract(string $filePath, string $mimeType): string
    {
        try {
            $data = Excel::toArray([], $filePath);
            $lines = [];
            foreach ($data as $sheet) {
                foreach ($sheet as $row) {
                    $rowValues = array_filter(array_map('trim', array_map('strval', $row)));
                    if (!empty($rowValues)) {
                        $lines[] = implode(' | ', $rowValues);
                    }
                }
            }
            return implode("\n", array_slice($lines, 0, 500)); // limit rows for sanity
        } catch (Throwable $e) {
            return '';
        }
    }
}
