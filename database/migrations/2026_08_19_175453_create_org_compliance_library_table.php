<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ported from G2G's `master_compliance` table (its `formName == 'complaince_library'`
 * branch of `App\Http\Controllers\settings\instituteDetailController`).
 *
 * IMPORTANT: LMS-K12 already has an UNRELATED `master_compliance` table (SQAA /
 * school-accreditation compliance tracker - confirmed different domain). This new
 * table is deliberately named `org_compliance_library` to avoid any collision with
 * that existing table/model/controller, which is left untouched.
 *
 * Schema drift fix: the G2G controller reads/writes `frequency` and
 * `custom_frequency_details` columns that are NOT present in G2G's own migration
 * file (they were added directly to the live DB). Both are included here since the
 * frontend depends on them. A `department` column is also added (the frontend
 * form lists department alongside assignee).
 *
 * Fix (2026-08-20): this migration originally FK-referenced `tbluser.id` (for
 * assigned_to/created_by/updated_by/deleted_by), which fails - `tbluser.id` is
 * `int(11)`, not `bigint unsigned` like these FK columns, and MySQL/MariaDB
 * rejects a mismatched-type foreign key with errno 150. That's why this
 * migration was still "Pending" (never successfully applied) despite being
 * written weeks ago - every prior attempt to run it failed at the ALTER TABLE
 * ADD CONSTRAINT step. Same reasoning as every other ported table this
 * session (see e.g. 2026_08_19_090000_create_s_users_skills_table.php): no
 * FK to tbluser here, just plain indexed columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('org_compliance_library', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 191)->index();
            $table->text('description')->nullable();
            $table->string('standard_name', 191)->nullable();
            $table->string('department', 191)->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->date('duedate')->nullable();
            $table->string('attachment', 191)->nullable();
            $table->string('frequency', 100)->nullable();
            $table->string('custom_frequency_details', 191)->nullable();
            $table->unsignedBigInteger('sub_institute_id')->index();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            $table->index('assigned_to');
            $table->index('created_by');
            $table->index('updated_by');
            $table->index('deleted_by');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('org_compliance_library');
    }
};
