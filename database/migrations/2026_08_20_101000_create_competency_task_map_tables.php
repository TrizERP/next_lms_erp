<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task Management > "What this task builds" (competency mapping) - schema.
 *
 * Ported from hp_erp's `App\Http\Controllers\Api\Competency\JobroleTaskCompetencyMapController`
 * (`forTask`/`store`), which this target reuses via
 * `App\Http\Controllers\api\TaskManagement\JobroleTaskCompetencyMapController`.
 * As of this migration, all four tables below are confirmed MISSING from
 * this database (checked via raw `SHOW TABLES LIKE` - `Schema::hasTable` is
 * blocked in this environment's tinker, not in migrations, where it is the
 * established convention - see `2026_08_20_100300_create_task_management_support_tables.php`).
 * `s_jobrole_task` and `s_user_jobrole_task` already exist and are reused
 * as-is - not touched here.
 *
 * `competency` and `competency_kasba_item`/`competency_kasba_rating` are
 * expected to hold 0 rows after this migration runs: the catalogue is
 * AUTHORED BY A CUSTOMER, NOT DERIVED (see the controller's class doc). An
 * empty table is the product working correctly, not unfinished work.
 *
 * Column shapes match the controller's actual query usage:
 *   - `competency`: id, sub_institute_id, name, code, criticality, deleted_at, timestamps
 *     (soft-deleted: `forTask`/`index`/`store` all `whereNull('deleted_at')`)
 *   - `competency_kasba_item`: id, sub_institute_id, competency_id, timestamps
 *     (KASBA = Knowledge/Attitude/Skill/Behaviour/Ability items beneath a competency)
 *   - `competency_kasba_rating`: id, kasba_item_id, user_id, rating, timestamps
 *     (one subject's rating on one KASBA item; rolled up per competency)
 *   - `jobrole_task_competency_map`: id, sub_institute_id, jobrole_task_id,
 *     competency_id, timestamps, unique [sub_institute_id, jobrole_task_id, competency_id]
 *     (the sync target `store()` upserts into and deletes stale rows from)
 *
 * Follows this backend's established Task Management migration conventions:
 * `bigIncrements`, `unsignedBigInteger(...)->index()`, no FK constraints,
 * timestamps, `createIfMissing` guard for a safely re-runnable migration.
 */
return new class extends Migration
{
    private function createIfMissing(string $table, \Closure $definition): void
    {
        if (! Schema::hasTable($table)) {
            Schema::create($table, $definition);
        }
    }

    public function up(): void
    {
        $this->createIfMissing('competency', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('sub_institute_id')->index();
            $table->string('name', 191);
            $table->string('code', 50)->nullable();
            $table->string('criticality', 30)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['sub_institute_id', 'name'], 'competency_tenant_name_idx');
        });

        $this->createIfMissing('competency_kasba_item', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('sub_institute_id')->index();
            $table->unsignedBigInteger('competency_id')->index();
            $table->timestamps();

            $table->index(['competency_id', 'sub_institute_id'], 'competency_kasba_item_competency_tenant_idx');
        });

        $this->createIfMissing('competency_kasba_rating', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('kasba_item_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedTinyInteger('rating');
            $table->timestamps();

            $table->index(['kasba_item_id', 'user_id'], 'competency_kasba_rating_item_user_idx');
        });

        $this->createIfMissing('jobrole_task_competency_map', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('sub_institute_id')->index();
            // References the GLOBAL s_jobrole_task catalogue (no FK: that
            // table carries no sub_institute_id, so this is a tenant-owned
            // mapping onto a shared library - see the controller's class doc.
            $table->unsignedBigInteger('jobrole_task_id')->index();
            $table->unsignedBigInteger('competency_id')->index();
            $table->timestamps();

            // Named: the auto-generated name exceeds MySQL's 64-char limit.
            $table->unique(['sub_institute_id', 'jobrole_task_id', 'competency_id'], 'jobrole_task_competency_map_tenant_task_comp_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jobrole_task_competency_map');
        Schema::dropIfExists('competency_kasba_rating');
        Schema::dropIfExists('competency_kasba_item');
        Schema::dropIfExists('competency');
    }
};
