<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lms_exam_proctoring_sessions', function (Blueprint $table) {
            $table->unique(
                ['id', 'exam_id', 'student_id'],
                'lms_exam_proctoring_session_mapping'
            );
        });

        Schema::table('lms_exam_proctoring_events', function (Blueprint $table) {
            $table->dropForeign('lms_exam_proctoring_event_session');
            $table->unsignedBigInteger('exam_id')->nullable();
            $table->unsignedBigInteger('question_paper_id')->nullable();
            $table->unsignedBigInteger('student_id')->nullable();
            $table->json('event_details')->nullable();
            $table->unsignedInteger('violation_count')->nullable();
            $table->uuid('event_key')->nullable()->unique('lms_exam_proctoring_event_key');
            $table->index(
                ['exam_id', 'question_paper_id', 'student_id', 'created_at'],
                'lms_exam_proctoring_event_mapping'
            );
            $table->index(
                ['student_id', 'created_at'],
                'lms_exam_proctoring_event_student_timeline'
            );
            $table->foreign(
                ['session_id', 'exam_id', 'student_id'],
                'lms_exam_proctoring_event_session_mapping'
            )->references(['id', 'exam_id', 'student_id'])
                ->on('lms_exam_proctoring_sessions')
                ->cascadeOnDelete();
        });

        DB::table('lms_exam_proctoring_events')
            ->whereNull('event_details')
            ->update(['event_details' => DB::raw('details')]);

        $violationCounts = [];
        DB::table('lms_exam_proctoring_events')
            ->select(['id', 'session_id', 'is_violation'])
            ->orderBy('id')
            ->chunkById(500, function ($events) use (&$violationCounts): void {
                $sessions = DB::table('lms_exam_proctoring_sessions')
                    ->whereIn('id', $events->pluck('session_id')->unique())
                    ->get(['id', 'exam_id', 'student_id'])
                    ->keyBy('id');

                foreach ($events as $event) {
                    $session = $sessions->get($event->session_id);
                    if (! $session) {
                        continue;
                    }

                    $violationCounts[$event->session_id] = ($violationCounts[$event->session_id] ?? 0)
                        + (int) $event->is_violation;
                    DB::table('lms_exam_proctoring_events')
                        ->where('id', $event->id)
                        ->update([
                            'exam_id' => $session->exam_id,
                            'question_paper_id' => $session->exam_id,
                            'student_id' => $session->student_id,
                            'violation_count' => $event->is_violation
                                ? $violationCounts[$event->session_id]
                                : null,
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('lms_exam_proctoring_events', function (Blueprint $table) {
            $table->dropForeign('lms_exam_proctoring_event_session_mapping');
            $table->dropIndex('lms_exam_proctoring_event_mapping');
            $table->dropIndex('lms_exam_proctoring_event_student_timeline');
            $table->dropUnique('lms_exam_proctoring_event_key');
            $table->dropColumn([
                'exam_id',
                'question_paper_id',
                'student_id',
                'event_details',
                'violation_count',
                'event_key',
            ]);
            $table->foreign('session_id', 'lms_exam_proctoring_event_session')
                ->references('id')
                ->on('lms_exam_proctoring_sessions')
                ->cascadeOnDelete();
        });

        Schema::table('lms_exam_proctoring_sessions', function (Blueprint $table) {
            $table->dropUnique('lms_exam_proctoring_session_mapping');
        });
    }
};
