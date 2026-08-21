<?php

namespace App\Rules\TaskManagement;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Http\UploadedFile;

/**
 * Fresh rule — the source (`App\Rules\TaskManagement\SafeCsvFile`, referenced
 * by hp_erp's `BulkTaskImportRequest`) was never actually committed there
 * either, so there was nothing to port. Written here from what its call site
 * implies: the uploaded file must genuinely be a CSV / plain-text file, not
 * merely named `*.csv`.
 *
 * Deliberately simple — MIME sniffing plus an extension check, no CSV
 * parsing — so a mislabelled Excel/HTML export is rejected before it reaches
 * the importer, without false-positiving on the many legitimate MIME strings
 * browsers send for CSV.
 */
class SafeCsvFile implements Rule
{
    private const ALLOWED_MIME_TYPES = [
        'text/plain',
        'text/csv',
        'application/csv',
        'application/vnd.ms-excel',
        'text/x-csv',
        'application/x-csv',
    ];

    public function passes($attribute, $value): bool
    {
        if (!$value instanceof UploadedFile || !$value->isValid()) {
            return false;
        }

        $extension = strtolower((string) $value->getClientOriginalExtension());
        if ($extension !== 'csv') {
            return false;
        }

        $mime = (string) $value->getMimeType();

        return in_array($mime, self::ALLOWED_MIME_TYPES, true);
    }

    public function message(): string
    {
        return 'The :attribute must be a genuine CSV file.';
    }
}
