<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Backfills attempt_number for pal_diagnostic_attempt rows submitted before
 * the column existed (see the companion add-column migration). Numbers
 * submitted attempts only, per (student_id, chapter_id), in submission
 * order - matching exactly what DiagnosticService::submit() now computes
 * for every attempt going forward.
 */
return new class extends Migration
{
    public function up(): void
    {
        $counters = [];

        DB::table('pal_diagnostic_attempt')
            ->where('status', 'submitted')
            ->whereNull('attempt_number')
            ->orderBy('student_id')
            ->orderBy('chapter_id')
            ->orderBy('id')
            ->select('id', 'student_id', 'chapter_id')
            ->cursor()
            ->each(function ($row) use (&$counters) {
                $key = $row->student_id . ':' . $row->chapter_id;
                $counters[$key] = ($counters[$key] ?? 0) + 1;

                DB::table('pal_diagnostic_attempt')
                    ->where('id', $row->id)
                    ->update(['attempt_number' => $counters[$key]]);
            });
    }

    /**
     * Deliberately a no-op: reversing would discard numbering with no way
     * to reconstruct what it was before, and leaving it does not conflict
     * with anything the add-column migration's down() does.
     */
    public function down(): void
    {
    }
};
