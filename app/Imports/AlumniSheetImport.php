<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToArray;

/**
 * Reads a spreadsheet as plain rows (Excel::toArray). Nothing is imported by this class; AlumniImportService turns the rows
 * into a validated, previewed batch.
 */
class AlumniSheetImport implements ToArray
{
    public function array(array $array): void
    {
    }
}
