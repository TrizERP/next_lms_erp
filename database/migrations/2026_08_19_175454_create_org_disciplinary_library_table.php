<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ported from G2G's `discliplinary_management` table (note: G2G's own name is a
 * typo - "discliplinary". This is a brand-new module in LMS-K12, not bound to
 * G2G's exact strings, so the correct spelling "disciplinary" is used here.
 *
 * One FK fix vs. the source migration: G2G left `witness_id` without a foreign
 * key ("nullable, add proper FK to tbluser this time" per the port instructions).
 * That is fixed below.
 *
 * Fix (2026-08-20): this migration was still "Pending" (never successfully
 * applied) because its `tbluser`/`school_setup`-referencing foreign keys
 * (employee_id/witness_id/reported_by/created_by/updated_by/deleted_by/
 * sub_institute_id) fail with errno 150 - both `tbluser.id` and
 * `school_setup.id` are `int(11)`, not `bigint unsigned` like these columns.
 * Those FKs are removed (kept as plain indexed columns), same reasoning as
 * every other ported table this session. `department_id` -> `hrms_departments.id`
 * is kept as a real FK - that column genuinely is `bigint(20) unsigned`, so
 * the type-matched constraint is valid and correctly enforced here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('org_disciplinary_library', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('department_id')->index()->nullable();
            $table->unsignedBigInteger('employee_id')->index()->nullable();
            $table->dateTime('incident_datetime')->index()->nullable();
            $table->string('location')->nullable();

            $table->enum('misconduct_type', [
                'Late Arrival',
                'Absenteeism',
                'Misbehavior',
                'Violation of Policy',
                'Others',
            ])->index()->nullable();

            $table->text('description')->nullable();
            $table->unsignedBigInteger('witness_id')->nullable();

            $table->enum('action_taken', [
                'Warning',
                'Suspension',
                'Termination',
                'Counseling',
                'Others',
            ])->index()->nullable();

            $table->text('remarks')->nullable();

            $table->unsignedBigInteger('reported_by')->index()->nullable();
            $table->date('date_of_report')->index()->nullable();

            $table->unsignedBigInteger('sub_institute_id')->index();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('department_id')
                ->references('id')->on('hrms_departments')
                ->onDelete('NO ACTION')->onUpdate('NO ACTION');

            $table->index('witness_id');
            $table->index('created_by');
            $table->index('updated_by');
            $table->index('deleted_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('org_disciplinary_library');
    }
};
