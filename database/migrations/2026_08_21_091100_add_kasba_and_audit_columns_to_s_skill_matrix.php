<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Judgment call for this Competency Management port (not one of the source
 * migrations explicitly listed for this task, but required for the ported
 * controllers to run against this schema's `s_skill_matrix`).
 *
 * G2G's `s_skill_matrix` picked up `type`, `knowledge`, `ability`,
 * `created_by`, `updated_by`, `deleted_by` and soft deletes over several of
 * its own migrations
 * (2025_06_11_100709_create_s_skill_matrix_table.php,
 *  2025_07_02_122809_add_colums_skill_matrix.php,
 *  2026_08_04_120000_align_s_skill_matrix_with_live_schema.php).
 * `EmployeeCompetencyProfileController` (show/addSkill/updateSkill/
 * skillHistory) and `DevelopmentPlanController::gaps`, ported line-for-line
 * from those G2G controllers, read/write every one of those columns
 * (`m.deleted_at`, `m.type`, `m.knowledge`, `m.ability`, `m.updated_by`) - so
 * without them those endpoints fail with "column not found" rather than
 * simply seeing no data.
 *
 * This target's own `2025_02_06_174323_create_s_skill_matrix_table.php` only
 * has `user_id`, `skill_id`, `skill_level`, `interest_level` and plain
 * timestamps. This migration closes that gap the same way G2G's own
 * `align_s_skill_matrix_with_live_schema` migration does: guarded,
 * `Schema::hasColumn()` checks so it is a no-op if the columns already exist,
 * purely additive/nullable so every existing reader of this table
 * (unrelated to Competency Management) is unaffected, and `down()` leaves the
 * columns in place since they may hold real data by the time this is rolled
 * back.
 */
return new class extends Migration
{
    private const KASBA_TYPES = ['skill', 'knowledge', 'ability', 'attitude', 'behaviour'];

    public function up(): void
    {
        if (!Schema::hasTable('s_skill_matrix')) {
            return;
        }

        Schema::table('s_skill_matrix', function (Blueprint $table) {
            if (!Schema::hasColumn('s_skill_matrix', 'type')) {
                $table->enum('type', self::KASBA_TYPES)->nullable()->after('user_id');
            }
            if (!Schema::hasColumn('s_skill_matrix', 'knowledge')) {
                $table->text('knowledge')->nullable()->after('interest_level');
            }
            if (!Schema::hasColumn('s_skill_matrix', 'ability')) {
                $table->text('ability')->nullable()->after('knowledge');
            }
            if (!Schema::hasColumn('s_skill_matrix', 'created_by')) {
                $table->unsignedBigInteger('created_by')->nullable()->index();
            }
            if (!Schema::hasColumn('s_skill_matrix', 'updated_by')) {
                $table->unsignedBigInteger('updated_by')->nullable()->index();
            }
            if (!Schema::hasColumn('s_skill_matrix', 'deleted_by')) {
                $table->unsignedBigInteger('deleted_by')->nullable();
            }
            if (!Schema::hasColumn('s_skill_matrix', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    /**
     * Deliberately does not drop these columns - see class doc-block.
     */
    public function down(): void
    {
        // no-op
    }
};
