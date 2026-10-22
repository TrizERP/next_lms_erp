<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The LMS "Quiz" feature's student-attempt table.
 *
 * Confirmed via information_schema (2026-10-10) that no table in this schema
 * stores a flexible, in-progress-then-completed student attempt against a
 * `question_paper` row the way this feature needs. `lms_online_exam` /
 * `lms_online_exam_answer` come closest (student_id + question_paper_id +
 * score columns, 149k/2.4M live rows) but they belong to the existing
 * "Online Exam" feature (onlineExamController): they are written once, at
 * submit time, with no in-progress row and no `completed_at`, and they are
 * NOT in config('neo4j.projections.triggered') — their volume is synced only
 * via the scheduled `neo4j:refresh-aggregates` pass, not a live per-row
 * trigger (see config/neo4j.php's note on aggregate sources). Reusing them
 * here would tie the new Quiz feature to that hot legacy path and its
 * aggregate-only graph sync, which is the wrong fit for a feature whose
 * attempts should hit the graph live (see the trigger migration alongside
 * this one). `lms_offline_exam` is the closer structural cousin — one row
 * per attempt, summary columns, its own live-sync entity (:OfflineExam) -
 * and this table mirrors its shape, adding only what that table lacks and
 * this feature needs: a `status` (in_progress/completed) so a row can exist
 * before it is scored, `started_at`/`completed_at` as two separate instants,
 * and `answers_json` holding the student's per-question answers so the
 * whole attempt is one row rather than a second per-question table — the
 * "likely one new table" the brief asked for.
 *
 * The quiz CONTENT is not duplicated here: a quiz is a `question_paper` row
 * (exam_type = 'quiz', an existing free-text column already carrying
 * 'online'/'offline'/'practice'/'term' in this schema) whose `question_ids`
 * point at real `lms_question_master` rows, scored against `answer_master`.
 * This table only records the attempt.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('lms_quiz_attempts')) {
            return;
        }

        Schema::create('lms_quiz_attempts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('question_paper_id');
            $table->integer('student_id');
            $table->integer('sub_institute_id')->nullable();
            $table->integer('syear')->nullable();

            // 'in_progress' from start until submit writes the score and flips
            // this to 'completed'. No other states exist yet (no save-and-resume,
            // no abandon detection) — deliberately minimal for v1.
            $table->string('status', 20)->default('in_progress');

            $table->unsignedInteger('total_questions')->nullable();
            $table->unsignedInteger('correct_count')->nullable();
            $table->unsignedInteger('wrong_count')->nullable();
            $table->unsignedInteger('unanswered_count')->nullable();
            $table->decimal('score_marks', 8, 2)->nullable();
            $table->decimal('max_marks', 8, 2)->nullable();

            // { "<question_id>": "<submitted answer_master id>", ... } plus a
            // parallel per-question correctness breakdown written at submit time
            // so the review screen does not have to re-derive it later.
            $table->longText('answers_json')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['question_paper_id', 'student_id'], 'idx_quiz_attempts_paper_student');
            $table->index(['student_id', 'sub_institute_id', 'syear'], 'idx_quiz_attempts_student_tenant');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lms_quiz_attempts');
    }
};
