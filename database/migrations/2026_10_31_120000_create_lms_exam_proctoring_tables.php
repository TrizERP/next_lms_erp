<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lms_exam_proctoring_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('sub_institute_id');
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('exam_id');
            $table->string('client_session_key', 36);
            $table->string('status', 20)->default('active');
            $table->dateTime('started_at');
            $table->dateTime('ended_at')->nullable();
            $table->dateTime('last_event_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['sub_institute_id', 'student_id', 'client_session_key'],
                'lms_exam_proctoring_session_key'
            );
            $table->index(
                ['sub_institute_id', 'exam_id', 'status', 'started_at'],
                'lms_exam_proctoring_monitoring'
            );
        });

        Schema::create('lms_exam_proctoring_events', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('session_id');
            $table->string('event_type', 40);
            $table->boolean('is_violation')->default(false);
            $table->dateTime('occurred_at');
            $table->json('details')->nullable();
            $table->timestamps();

            $table->foreign('session_id', 'lms_exam_proctoring_event_session')
                ->references('id')
                ->on('lms_exam_proctoring_sessions')
                ->cascadeOnDelete();
            $table->index(['session_id', 'occurred_at'], 'lms_exam_proctoring_event_timeline');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lms_exam_proctoring_events');
        Schema::dropIfExists('lms_exam_proctoring_sessions');
    }
};
