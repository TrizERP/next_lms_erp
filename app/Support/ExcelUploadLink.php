<?php

namespace App\Support;

/**
 * Signed key for the standalone bulk-import pages in public/excel_upload.
 * Verified by bootstrap/excel_upload_guard.php with the same APP_KEY.
 */
class ExcelUploadLink
{
    public static function key(int $subInstituteId, int $userId = 0, int $ttlSeconds = 43200): string
    {
        $exp = time() + $ttlSeconds;

        return $exp . '.' . hash_hmac('sha256', 'excel_upload|' . $subInstituteId . '|' . $userId . '|' . $exp, (string) config('app.key'));
    }
}
