<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Support schema for the ported `KasbaRatingController` (Employee Profiles'
 * "Rate KASBA items" panel) - discovered missing after the frontend's 404
 * against `/api/competency/kasba-rating` surfaced that this controller was
 * not in the original Competency Management port scope.
 *
 * `competency`, `competency_kasba_item` and `competency_kasba_rating` already
 * exist in this schema (created by
 * `2026_08_20_101000_create_competency_task_map_tables.php` for Task
 * Management's competency mapping feature) but are missing columns
 * `KasbaRatingController::index()/store()` reads and writes. `jobrole_
 * competency_map` (what a job role requires - Q-C1 in the G2G source) does
 * not exist at all yet. All changes here are purely additive/guarded so the
 * existing Task Management feature built on these three tables is untouched.
 *
 * Column shapes match G2G's source migrations exactly
 * (`2026_08_07_100000_phase3_foundation_join_tables.php`,
 *  `2026_08_10_180000_fix_kasba_item_label.php`,
 *  `2026_08_10_190000_create_competency_kasba_rating.php`), minus the
 * columns/constraints this schema's existing tables already satisfy
 * differently (e.g. `competency_kasba_item.item_id` is not needed by this
 * controller and is left out to avoid colliding with Task Management's own
 * use of the table).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competency_kasba_item', function (Blueprint $table) {
            if (!Schema::hasColumn('competency_kasba_item', 'kasba_type')) {
                $table->enum('kasba_type', ['skill', 'knowledge', 'ability', 'attitude', 'behaviour'])
                    ->nullable()->after('competency_id');
            }
            if (!Schema::hasColumn('competency_kasba_item', 'item_label')) {
                $table->string('item_label', 191)->nullable()->after('kasba_type');
            }
            if (!Schema::hasColumn('competency_kasba_item', 'weight')) {
                $table->decimal('weight', 5, 2)->default(1.00)->after('item_label');
            }
        });

        Schema::table('competency_kasba_rating', function (Blueprint $table) {
            if (!Schema::hasColumn('competency_kasba_rating', 'sub_institute_id')) {
                $table->unsignedBigInteger('sub_institute_id')->nullable()->index()->after('id');
            }
            if (!Schema::hasColumn('competency_kasba_rating', 'assessor_id')) {
                $table->unsignedBigInteger('assessor_id')->nullable()->after('rating');
            }
            if (!Schema::hasColumn('competency_kasba_rating', 'source')) {
                $table->string('source', 32)->default('manual')->after('assessor_id');
            }
            if (!Schema::hasColumn('competency_kasba_rating', 'note')) {
                $table->text('note')->nullable()->after('source');
            }
            if (!Schema::hasColumn('competency_kasba_rating', 'rated_at')) {
                $table->dateTime('rated_at')->nullable()->after('note');
            }
        });

        if (!Schema::hasTable('jobrole_competency_map')) {
            Schema::create('jobrole_competency_map', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('sub_institute_id')->index();
                $table->unsignedBigInteger('jobrole_id');       // s_user_jobrole.id / tbluser.jobtitle_id
                $table->unsignedBigInteger('competency_id');
                $table->unsignedTinyInteger('required_proficiency')->nullable();
                $table->boolean('is_mandatory')->default(true);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->unique(['sub_institute_id', 'jobrole_id', 'competency_id'], 'uq_jcm');
                $table->index('competency_id', 'idx_jcm_competency');
            });
        }
    }

    /**
     * Deliberately does not drop columns/the table - see class doc-block on
     * the sibling `2026_08_21_091100_add_kasba_and_audit_columns_to_s_skill_matrix`
     * migration for why (they may hold real data by the time this rolls back).
     */
    public function down(): void
    {
        // no-op
    }
};
