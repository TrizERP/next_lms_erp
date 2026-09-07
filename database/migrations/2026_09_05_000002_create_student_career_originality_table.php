<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * student_career_originality
 * ---------------------------
 * "Career Originality" (CI-GUIDE-DEV-001, Career Awareness Level-3). Free-text
 * reflection on how original/distinct the student's career aspiration is
 * relative to peers/family/circumstance — a narrative capture, not scored.
 *
 * Originality is a SNAPSHOT SERIES, same as aspirations/ambitions: a student
 * re-answers over time and every snapshot is kept, with is_current flagging
 * the latest. Never UPDATE a snapshot in place — insert a new row, flip
 * is_current.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_career_originality', function (Blueprint $table) {
            $table->id();

            $table->string('student_id')->index();
            $table->unsignedTinyInteger('grade');
            $table->string('academic_year', 9);              // e.g. "2026-2027"

            $table->text('originality_statement')->nullable(); // free-text reflection
            $table->text('originality_reason')->nullable();     // free-text: why

            $table->enum('source', ['student_form', 'counsellor_entry', 'parent_form'])
                  ->default('student_form');
            $table->boolean('is_current')->default(true);    // latest snapshot for this student
            $table->timestamp('captured_at');

            $table->timestamps();

            $table->index(['student_id', 'is_current']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_career_originality');
    }
};
