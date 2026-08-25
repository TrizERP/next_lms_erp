<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive columns for the ported `CompetencyLibraryCrudController` (G2G's
 * `CompetencyLibraryCrudController` on `/competency-library/*`).
 *
 * `competency` and `competency_kasba_item` already exist in this schema
 * (created by `2026_08_20_101000_create_competency_task_map_tables.php` for
 * Task Management's "what this task builds" feature, extended by
 * `2026_08_21_130000_add_kasba_rating_support_columns.php` for the ported
 * `KasbaRatingController`), but neither carries every column this second
 * controller's queries need:
 *
 *   - `competency` is missing `description`, `competency_type`, `framework_id`
 *     (FK-free link to `s_competency_frameworks`, the only framework table
 *     this port created), `status`, `created_by`, `updated_by` and `deleted_by`
 *     (schema per G2G's own `2026_08_07_100000_phase3_foundation_join_tables.php`,
 *     minus columns this port's controller never reads: `requires_assessment`,
 *     `version`). Confirmed via `php artisan db:table competency` that the live
 *     table has only `id, sub_institute_id, name, code, criticality,
 *     created_at, updated_at, deleted_at` - the original docblock's claim that
 *     `created_by`/`deleted_by` already existed was wrong; corrected here.
 *   - `competency_kasba_item` is missing `item_id` (the resolved-target half
 *     of the item_id/item_label pair `store()`/`update()` both accept).
 *
 * Purely additive and guarded, so the existing Task Management + KASBA rating
 * features built on these two tables are untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competency', function (Blueprint $table) {
            if (!Schema::hasColumn('competency', 'description')) {
                $table->text('description')->nullable()->after('name');
            }
            if (!Schema::hasColumn('competency', 'competency_type')) {
                $table->string('competency_type', 64)->nullable()->after('description');
            }
            if (!Schema::hasColumn('competency', 'framework_id')) {
                $table->unsignedBigInteger('framework_id')->nullable()->index()->after('criticality');
            }
            if (!Schema::hasColumn('competency', 'status')) {
                $table->string('status', 32)->default('active')->after('framework_id');
            }
            if (!Schema::hasColumn('competency', 'created_by')) {
                $table->unsignedBigInteger('created_by')->nullable()->index();
            }
            if (!Schema::hasColumn('competency', 'updated_by')) {
                $table->unsignedBigInteger('updated_by')->nullable();
            }
            if (!Schema::hasColumn('competency', 'deleted_by')) {
                $table->unsignedBigInteger('deleted_by')->nullable();
            }
        });

        Schema::table('competency_kasba_item', function (Blueprint $table) {
            if (!Schema::hasColumn('competency_kasba_item', 'item_id')) {
                $table->unsignedBigInteger('item_id')->nullable()->after('kasba_type');
            }
        });
    }

    /**
     * Deliberately does not drop columns - matches this module's other
     * additive migrations (see `2026_08_21_130000_add_kasba_rating_support_columns.php`):
     * by the time this rolls back these columns may hold real data.
     */
    public function down(): void
    {
        // no-op
    }
};
