<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * G2G LMS (Package 1) — bring `sub_std_map` up to the column set the ported
 * Learning Catalog / My Learning / Learning Dashboard controllers expect.
 *
 * `sub_std_map` is REUSED as-is (per the migration plan for this package) —
 * this only ADDS columns, never renames or drops one, so every existing
 * reader of this table (school_setup/master_setup, the native LMS under
 * routes/lms.php, etc.) is unaffected.
 *
 * ── APPROVED LIST ────────────────────────────────────────────────────────
 * `load`, `optional_type`, `proficiency`, `content_quantity`,
 * `certificate_validity_months`, `jobrole`, `created_by`/`updated_by`/
 * `deleted_by`, and soft-deletes (`deleted_at`) — all present in hp_erp's
 * `sub_std_map` (2025_06_02_093430_create_sub_std_map_table.php plus its
 * later ALTER migrations) and missing from this project's copy
 * (2023_03_05_115658_create_sub_std_map_table.php).
 *
 * ── DEVIATION: subject_code / subject_type / short_name ADDED TOO ───────
 * These three are NOT in the task's explicit approved list, but
 * `LmsCourseController` (ported here as `LearningCatalogController`) reads,
 * searches, filters and writes all three on almost every endpoint
 * (index's search/select, filters()'s distinct('subject_type'), store()/
 * update()'s validation + $editable list). No ALTER migration for them was
 * found anywhere in hp_erp's `database/migrations` either — like `jobrole`,
 * they exist on hp_erp's live/dev schema without a tracked migration (this
 * codebase has several tables with that kind of drift; see e.g. the
 * `lms_course_enroll` and `s_users_skills` migration doc-blocks in this same
 * project). Without them the catalog's index/store/update/filters endpoints
 * would fail outright ("column not found"), so they are added here on the
 * same additive, nullable basis as the approved columns. Flagged in the
 * Package 1 report as a deviation from the literal column list.
 *
 * ── DEVIATION: subject_id relaxed to nullable, standard_id left alone ───
 * hp_erp's `subject_id`/`standard_id` are both nullable; this project's are
 * `unsignedInteger` NOT NULL with no default (2023_03_05_115658). The task
 * allows skipping this relaxation when it looks risky - and `standard_id`
 * IS skipped: `LearningCatalogController::store/update` always validates and
 * writes it (`required|integer`), so nothing in this port needs it relaxed,
 * and leaving a column other readers (school_setup/master_setup, the native
 * LMS) may assume is populated is the safer choice.
 *
 * `subject_id`, however, is NOT set by `LearningCatalogController::store()`
 * at all - ported faithfully from hp_erp's `LmsCourseController::store()`,
 * which never assigns it either (a course's `subject_id` is polymorphic and
 * unrelated to how the catalog creates a course; see
 * `LmsLearningController::resolveCourseSkillId()`'s doc-block). Leaving this
 * column NOT NULL would make every single course-creation call in this
 * package fail outright with a database error, which is a correctness bug,
 * not a mere risk - so it is relaxed to nullable via raw SQL (no default
 * change, no data touched; every existing row already has a value).
 *
 * ── DEVIATION: no FK constraints on created_by/updated_by/deleted_by ────
 * `tbluser.id` is declared `bigIncrements` in this project's own migration,
 * but a sibling migration in this same codebase
 * (2026_08_19_090000_create_s_users_skills_table.php) found the *live*
 * column to actually be `int unsigned`, not `bigint unsigned`, and skipped
 * the FK for exactly that reason. Given that precedent, these three columns
 * are added as plain indexed `unsignedBigInteger` columns with no FK, to
 * avoid a migration failure if this schema has the same drift.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sub_std_map', function (Blueprint $table) {
            if (! Schema::hasColumn('sub_std_map', 'load')) {
                $table->string('load', 20)->nullable()->after('display_name');
            }
            if (! Schema::hasColumn('sub_std_map', 'optional_type')) {
                $table->string('optional_type', 20)->nullable()->after('load');
            }
            if (! Schema::hasColumn('sub_std_map', 'subject_code')) {
                $table->string('subject_code', 100)->nullable()->after('subject_category');
            }
            if (! Schema::hasColumn('sub_std_map', 'subject_type')) {
                $table->string('subject_type', 100)->nullable()->after('subject_code');
            }
            if (! Schema::hasColumn('sub_std_map', 'short_name')) {
                $table->string('short_name', 100)->nullable()->after('subject_type');
            }
            if (! Schema::hasColumn('sub_std_map', 'jobrole')) {
                $table->string('jobrole', 191)->nullable()->after('short_name');
            }
            if (! Schema::hasColumn('sub_std_map', 'proficiency')) {
                $table->string('proficiency', 191)->nullable()->after('jobrole');
            }
            if (! Schema::hasColumn('sub_std_map', 'content_quantity')) {
                $table->integer('content_quantity')->nullable()->after('allow_content');
            }
            if (! Schema::hasColumn('sub_std_map', 'certificate_validity_months')) {
                $table->unsignedSmallInteger('certificate_validity_months')->nullable()->after('content_quantity');
            }
            if (! Schema::hasColumn('sub_std_map', 'created_by')) {
                $table->unsignedBigInteger('created_by')->nullable()->index();
            }
            if (! Schema::hasColumn('sub_std_map', 'updated_by')) {
                $table->unsignedBigInteger('updated_by')->nullable()->index();
            }
            if (! Schema::hasColumn('sub_std_map', 'deleted_by')) {
                $table->unsignedBigInteger('deleted_by')->nullable()->index();
            }
            if (! Schema::hasColumn('sub_std_map', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        // Raw SQL rather than Blueprint::change() to avoid taking on
        // doctrine/dbal as a hard requirement for this one column. Guarded so
        // re-running (or running after someone else already relaxed it) is a
        // no-op.
        $column = DB::selectOne(
            "SELECT IS_NULLABLE FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sub_std_map' AND COLUMN_NAME = 'subject_id' LIMIT 1"
        );

        if ($column && strtoupper($column->IS_NULLABLE) === 'NO') {
            DB::statement('ALTER TABLE `sub_std_map` MODIFY `subject_id` INT UNSIGNED NULL');
        }
    }

    public function down(): void
    {
        Schema::table('sub_std_map', function (Blueprint $table) {
            foreach ([
                'load', 'optional_type', 'subject_code', 'subject_type', 'short_name',
                'jobrole', 'proficiency', 'content_quantity', 'certificate_validity_months',
                'created_by', 'updated_by', 'deleted_by',
            ] as $column) {
                if (Schema::hasColumn('sub_std_map', $column)) {
                    $table->dropColumn($column);
                }
            }
            if (Schema::hasColumn('sub_std_map', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
