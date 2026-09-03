<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * GR No. (enrollment_no) must be unique per institute and is no longer
 * scoped to admission year (see StudentRegistrationApiController), so the
 * uniqueness guard the application relies on needs to live in the DB, not
 * just in the pre-insert `exists()` check — that check alone is racy under
 * concurrent admissions.
 *
 * Existing data may already contain duplicate (sub_institute_id,
 * enrollment_no) pairs from before this constraint existed (blank/NULL
 * values, or legacy rows created outside this flow). Adding a unique index
 * over duplicate data fails the whole migration, so this skips the index
 * and logs the offending rows instead of leaving the migration unrunnable —
 * clean up the flagged duplicates, then re-run migrate to pick up the
 * constraint.
 */
return new class extends Migration
{
    private const INDEX_NAME = 'tblstudent_sub_institute_enrollment_no_unique';

    public function up(): void
    {
        $duplicates = DB::table('tblstudent')
            ->select('sub_institute_id', 'enrollment_no', DB::raw('COUNT(*) as total'))
            ->whereNotNull('enrollment_no')
            ->where('enrollment_no', '!=', '')
            ->groupBy('sub_institute_id', 'enrollment_no')
            ->having('total', '>', 1)
            ->get();

        if ($duplicates->isNotEmpty()) {
            Log::warning('Skipping tblstudent enrollment_no unique index: duplicate GR numbers exist.', [
                'duplicates' => $duplicates->toArray(),
            ]);
            return;
        }

        Schema::table('tblstudent', function ($table) {
            $table->unique(['sub_institute_id', 'enrollment_no'], self::INDEX_NAME);
        });
    }

    public function down(): void
    {
        // up() may have skipped creating the index (duplicates present), so
        // dropping it unconditionally would fail; ignore a missing index.
        try {
            Schema::table('tblstudent', function ($table) {
                $table->dropUnique(self::INDEX_NAME);
            });
        } catch (\Throwable $e) {
            // Index was never created — nothing to roll back.
        }
    }
};
