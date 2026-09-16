<?php

namespace App\Domain\AI\Templates;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The signed-in school's name and logo, for a template screen's letterhead.
 *
 * WHY IT READS THE FEE RECEIPT BOOK
 *
 * There is no table on this estate that reliably holds one name and one logo per
 * sub-institute. `school_setup` has `SchoolName` and `Logo` but no
 * `sub_institute_id` at all, so it cannot answer "which school is this"; `org_details`
 * and `tblclient` sit above the sub-institute, not on it.
 *
 * `fees_receipt_book_master` does, and it is the right source rather than a convenient
 * one: it is the letterhead the school already prints on every fee receipt, so a
 * template screen branded from it matches the documents the school actually sends.
 * `receipt_line_1` is the school's name as the school chose to write it, and
 * `receipt_logo` is the image it prints beside it.
 *
 * WHY `receipt_line_1` BEATS `fees_config_master.institute_name`
 *
 * Both exist and they disagree. On this estate institute 61's `institute_name` is the
 * single character "." while its `receipt_line_1` is "Lions English School". The
 * receipt line is maintained because it is printed and read; the config name is not.
 * So the receipt line is tried first and the config name is the fallback, not the
 * other way round.
 *
 * NOTHING IS HARDCODED AND NOTHING IS INVENTED
 *
 * Every value is read for the institute on the caller's token. Where a school has
 * filled none of them in, the name comes back null and the caller shows its own
 * neutral default — which is honest, and better than printing another school's name.
 */
class InstituteBranding
{
    /** Where `receipt_logo` files are served from, matching the admission controllers. */
    private const LOGO_PATH = '/storage/fees/';

    /**
     * @return array{institute_name:?string, logo_url:?string, sub_institute_id:int|string|null}
     */
    public function forInstitute(int|string|null $subInstituteId): array
    {
        $branding = [
            'institute_name' => null,
            'logo_url' => null,
            'sub_institute_id' => $subInstituteId,
        ];

        if ($subInstituteId === null || $subInstituteId === '') {
            return $branding;
        }

        $receipt = $this->receiptBook($subInstituteId);

        $branding['institute_name'] = $this->firstNonEmpty([
            $receipt->receipt_line_1 ?? null,
            $this->configuredName($subInstituteId),
        ]);

        $logo = trim((string) ($receipt->receipt_logo ?? ''));

        if ($logo !== '') {
            // Stored as a bare filename. Absolute URLs are passed through untouched in
            // case an estate has already migrated to remote storage.
            $branding['logo_url'] = str_starts_with($logo, 'http://') || str_starts_with($logo, 'https://')
                ? $logo
                : self::LOGO_PATH . ltrim($logo, '/');
        }

        return $branding;
    }

    /**
     * The school's most recent receipt book.
     *
     * Ordered by year because a school keeps one row per academic year and the newest
     * carries the current letterhead. `status` is not filtered: a book can be closed for
     * new receipts while still being the school's identity.
     */
    private function receiptBook(int|string $subInstituteId): object
    {
        if (! Schema::hasTable('fees_receipt_book_master')) {
            return (object) [];
        }

        return DB::table('fees_receipt_book_master')
            ->where('sub_institute_id', $subInstituteId)
            ->orderByDesc('syear')
            ->orderByDesc('id')
            ->first(['receipt_line_1', 'receipt_logo'])
            ?? (object) [];
    }

    private function configuredName(int|string $subInstituteId): ?string
    {
        if (! Schema::hasTable('fees_config_master')) {
            return null;
        }

        $name = DB::table('fees_config_master')
            ->where('sub_institute_id', $subInstituteId)
            ->orderByDesc('syear')
            ->value('institute_name');

        return $name === null ? null : (string) $name;
    }

    /**
     * The first value that is actually a name.
     *
     * Trimmed and length-checked rather than just non-empty, because "." is a value
     * this estate really holds and it is not a school's name.
     *
     * @param  array<int, string|null>  $candidates
     */
    private function firstNonEmpty(array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            $value = trim((string) $candidate);

            if ($value !== '' && mb_strlen($value) > 1) {
                return $value;
            }
        }

        return null;
    }
}
