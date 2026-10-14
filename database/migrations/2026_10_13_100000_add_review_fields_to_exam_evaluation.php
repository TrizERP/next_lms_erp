<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Two additions to the exam-evaluation tables, and nothing else.
 *
 *  1. exam_evaluation_answer.teacher_feedback -- what a teacher writes against
 *     one question. Until now the only text on an answer was the AI's own
 *     remark, so a teacher who changed a mark had nowhere to say why. It is
 *     nullable, and kept apart from `ai_remark` so the two are never confused:
 *     one is a proposal, the other is the teacher's word.
 *
 *  2. A unique (batch_id, student_id) on exam_evaluation_sheet -- one answer
 *     sheet per student per evaluation. The application already refuses a
 *     second sheet for a student when a teacher assigns one, but the matcher
 *     assigns students on its own while sheets are read in the background, and
 *     two jobs can finish together; only the database can settle that race.
 *     `student_id` is NULL until a sheet is matched, and the index allows any
 *     number of NULLs, so unmatched sheets are unaffected.
 *
 * Existing data may already hold two sheets for one student in a batch. Adding
 * a unique index over duplicates fails the whole migration, so -- as with the
 * tblstudent enrollment_no index -- the index is skipped and the offending rows
 * are logged instead of leaving the migration unrunnable. Re-open or delete the
 * extra sheets, then run migrate again to pick it up.
 *
 * Every step is guarded, so running this twice, or against a database that has
 * only some of it, is safe.
 */
return new class extends Migration
{
    private const UNIQUE_INDEX = 'ees_batch_student_unique';

    public function up(): void
    {
        if (Schema::hasTable('exam_evaluation_answer') && ! Schema::hasColumn('exam_evaluation_answer', 'teacher_feedback')) {
            Schema::table('exam_evaluation_answer', function (Blueprint $table) {
                $table->text('teacher_feedback')->nullable()->after('ai_remark');
            });
        }

        if (! Schema::hasTable('exam_evaluation_sheet') || $this->hasIndex('exam_evaluation_sheet', self::UNIQUE_INDEX)) {
            return;
        }

        $duplicates = DB::table('exam_evaluation_sheet')
            ->select('batch_id', 'student_id', DB::raw('COUNT(*) as total'))
            ->whereNotNull('student_id')
            ->groupBy('batch_id', 'student_id')
            ->having('total', '>', 1)
            ->get();

        if ($duplicates->isNotEmpty()) {
            Log::warning('Skipping exam_evaluation_sheet (batch_id, student_id) unique index: duplicate sheets exist.', [
                'duplicates' => $duplicates->toArray(),
            ]);

            return;
        }

        Schema::table('exam_evaluation_sheet', function (Blueprint $table) {
            $table->unique(['batch_id', 'student_id'], self::UNIQUE_INDEX);
        });
    }

    public function down(): void
    {
        // up() may have skipped the index (duplicates present), so dropping it
        // unconditionally would fail.
        if (Schema::hasTable('exam_evaluation_sheet') && $this->hasIndex('exam_evaluation_sheet', self::UNIQUE_INDEX)) {
            Schema::table('exam_evaluation_sheet', function (Blueprint $table) {
                $table->dropUnique(self::UNIQUE_INDEX);
            });
        }

        if (Schema::hasTable('exam_evaluation_answer') && Schema::hasColumn('exam_evaluation_answer', 'teacher_feedback')) {
            Schema::table('exam_evaluation_answer', function (Blueprint $table) {
                $table->dropColumn('teacher_feedback');
            });
        }
    }

    private function hasIndex(string $table, string $index): bool
    {
        foreach (Schema::getIndexes($table) as $existing) {
            if (($existing['name'] ?? null) === $index) {
                return true;
            }
        }

        return false;
    }
};
