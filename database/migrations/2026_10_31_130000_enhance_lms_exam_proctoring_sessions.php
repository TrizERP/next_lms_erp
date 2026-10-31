<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lms_exam_proctoring_sessions', function (Blueprint $table) {
            $table->json('answer_draft')->nullable();
            $table->unsignedInteger('tab_switch_count')->default(0);
            $table->unsignedInteger('violation_count')->default(0);
            $table->unsignedInteger('critical_violation_count')->default(0);
            $table->dateTime('expires_at')->nullable();
            $table->dateTime('last_heartbeat_at')->nullable();
            $table->string('auto_submit_reason', 80)->nullable();
            $table->unsignedBigInteger('online_exam_id')->nullable();
            $table->index(
                ['sub_institute_id', 'student_id', 'exam_id', 'status'],
                'lms_exam_proctoring_active_student'
            );
        });

        Schema::table('lms_exam_proctoring_events', function (Blueprint $table) {
            $table->string('severity', 20)->default('info');
        });
    }

    public function down(): void
    {
        Schema::table('lms_exam_proctoring_sessions', function (Blueprint $table) {
            $table->dropIndex('lms_exam_proctoring_active_student');
            $table->dropColumn([
                'answer_draft',
                'tab_switch_count',
                'violation_count',
                'critical_violation_count',
                'expires_at',
                'last_heartbeat_at',
                'auto_submit_reason',
                'online_exam_id',
            ]);
        });

        Schema::table('lms_exam_proctoring_events', function (Blueprint $table) {
            $table->dropColumn('severity');
        });
    }
};
