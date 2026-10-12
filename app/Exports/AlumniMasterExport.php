<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

/** Alumni Master Report as an .xlsx. Rows arrive already privacy-masked and formula-safe. */
class AlumniMasterExport implements FromArray, WithHeadings, ShouldAutoSize
{
    /** @param array<int, array<int, string|int|null>> $rows @param array<int, string> $headings */
    public function __construct(private array $rows, private array $headings)
    {
    }

    public function array(): array
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return $this->headings;
    }
}
