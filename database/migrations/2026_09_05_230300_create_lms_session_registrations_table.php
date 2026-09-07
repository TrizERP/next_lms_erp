<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * G2G LMS migration Ã¢â‚¬â€ Package 2 (Assignments, Sessions & Calendar).
 *
 * Who is on a training session (`lms_virtual_classroom`). Ported from
 * hp_erp's `2026_07_28_150001_create_lms_session_registrations_table`,
 * unchanged: a learner may self-register for an open session, an admin may
 * register or remove people directly, and cancelling keeps the row
 * (status = 'cancelled') rather than deleting it, so history survives Ã¢â‚¬â€
 * only 'registered'/'attended' rows consume a seat.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('lms_session_registrations')) {
            return;
        }
Schema::create('lms_session_registrations', function (Blueprint $table) {
            $table->bigIncrements('id');

            // lms_virtual_classroom.id
            $table->unsignedBigInteger('session_id')->index();
            $table->unsignedBigInteger('user_id')->index();

            $table->enum('status', ['registered', 'attended', 'cancelled', 'no-show'])
                ->default('registered')
                ->index();

            $table->timestamp('registered_at')->nullable();
            // Set when an admin adds someone rather than the learner signing up.
            $table->unsignedBigInteger('registered_by')->nullable();

            $table->unsignedBigInteger('sub_institute_id')->nullable()->index();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // One live registration per learner per session; re-registering
            // after cancelling reuses the row.
            $table->unique(['session_id', 'user_id'], 'lms_session_reg_session_user_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lms_session_registrations');
    }
};
