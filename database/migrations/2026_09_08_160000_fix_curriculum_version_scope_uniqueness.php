<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Makes the curriculum-version scope key actually unique.
 *
 * 2026_09_08_130000 put `subject_id` in the scope UNIQUE index while leaving it
 * nullable. MySQL treats NULLs as DISTINCT in a unique index, so the one case
 * the column is nullable FOR — a version covering a whole standard, where a
 * board publishes one syllabus for it — was the case the index did not
 * constrain. CurriculumVersionService::resolve() documents itself as
 * idempotent, and for standard-wide versions it was not: two callers racing
 * would each create a row, and "which syllabus is in force" would have two
 * answers.
 *
 * Fixed with a sentinel rather than a generated column: `subject_id = 0` means
 * "the whole standard". 0 is not a valid `subject.id`, so it cannot collide
 * with a real subject, and a plain NOT NULL column keeps the unique index
 * working the way every other scope key in this schema does.
 *
 * Existing NULLs are migrated to 0 first. Duplicates created before this point
 * would block the index, so they are reported rather than silently merged —
 * collapsing two curriculum versions is a content decision, not a migration's
 * to make.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pal_curriculum_versions')) {
            return;
        }

        DB::table('pal_curriculum_versions')->whereNull('subject_id')->update(['subject_id' => 0]);

        $duplicates = DB::table('pal_curriculum_versions')
            ->select('sub_institute_id', 'board', 'standard_id', 'subject_id', 'academic_year', DB::raw('COUNT(*) as n'))
            ->groupBy('sub_institute_id', 'board', 'standard_id', 'subject_id', 'academic_year')
            ->having('n', '>', 1)
            ->get();

        if ($duplicates->isNotEmpty()) {
            throw new RuntimeException(
                'pal_curriculum_versions has ' . $duplicates->count() . ' duplicated scope(s) that the unique index '
                . 'would reject. Merging two curriculum versions is a content decision, so this migration will not '
                . 'guess: resolve them by hand, then re-run.'
            );
        }

        Schema::table('pal_curriculum_versions', function (Blueprint $table) {
            $table->unsignedBigInteger('subject_id')->default(0)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('pal_curriculum_versions')) {
            return;
        }

        Schema::table('pal_curriculum_versions', function (Blueprint $table) {
            $table->unsignedBigInteger('subject_id')->nullable()->change();
        });

        DB::table('pal_curriculum_versions')->where('subject_id', 0)->update(['subject_id' => null]);
    }
};
