<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trainers who deliver sessions and courses, for G2G-LMS Administration &
 * Governance.
 *
 * Ported from hp_erp's `2026_07_29_100000_create_lms_trainers_table.php`
 * (`App\Http\Controllers\Api\LmsPartnerController`). New model - nothing in
 * this schema represents a trainer as an entity; `lms_virtual_classroom`
 * (the existing K12 session table) carries no trainer_email/trainer_name at
 * all, let alone a link. `user_id` links the ones who are also tbluser
 * employees; stays null for external contractors. `vendor_id` points at
 * `lms_vendors` (previous migration). Neither is a hard FK, matching this
 * package's no-FK convention (mirrors lms_vendors/lms_integrations).
 *
 * Cross-package note: Package 2 is separately adding a nullable `trainer_id`
 * column to `lms_virtual_classroom` intended to FK into this table's `id`.
 * See the follow-up migration (if Package 2's column had landed by the time
 * this package finished) or the final report (if it had not).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('lms_trainers')) {
            return;
        }
Schema::create('lms_trainers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sub_institute_id')->index();
            /** tbluser.id when the trainer is an employee; null for externals. */
            $table->unsignedBigInteger('user_id')->nullable()->index();

            $table->string('name', 191);
            $table->string('email', 191)->nullable();
            $table->string('phone', 50)->nullable();
            /** 'internal' or 'external' - drives whether a vendor applies. */
            $table->string('trainer_type', 20)->default('internal');
            /** lms_vendors.id for contracted trainers. */
            $table->unsignedBigInteger('vendor_id')->nullable()->index();

            $table->string('specialisation', 191)->nullable();
            $table->text('bio')->nullable();
            $table->text('qualifications')->nullable();
            $table->decimal('hourly_rate', 10, 2)->nullable();
            $table->string('currency', 10)->nullable();

            $table->boolean('status')->default(true);

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lms_trainers');
    }
};
