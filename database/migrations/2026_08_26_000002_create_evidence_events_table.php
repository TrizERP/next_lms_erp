<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * evidence_events — the shared Student Evidence Ledger
 * -----------------------------------------------------
 * APPEND-ONLY. No UPDATE. No DELETE. Corrections are new rows with contested=true
 * on the prior row's id (via superseded_by). This is the substrate for Capability,
 * Interest and Aspiration engines — it belongs to Student Intelligence, not to
 * Career alone.
 *
 * Every row is produced by an INGESTION ADAPTER (assessment, PAL, teacher-diary, …).
 * Career Intelligence reads THIS table, never the source module's tables.
 *
 * Adapter law (enforced in the adapter, verified against taxonomy/signal_validity.yaml):
 * a row may only assert what its source can defend. e.g. an attendance adapter may
 * NOT write a "conscientiousness" claim; a correctness signal may NOT write a
 * Behaviour/Attitude claim.
 *
 * NOTE: no 'performance level' percentages are stored. performance_level is a
 * descriptor level, per DEC (KASBA percentages killed).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evidence_events', function (Blueprint $table) {
            $table->uuid('evidence_id')->primary();

            $table->string('student_id')->index();
            $table->string('academic_year', 9);
            $table->unsignedTinyInteger('grade');

            // --- where it came from ---
            $table->enum('source_type', [
                'assessment', 'pal', 'exam', 'lms', 'teacher_diary', 'project',
                'activity', 'competition', 'sports', 'certificate', 'reflection',
                'psychometric', 'riasec', 'counsellor', 'parent', 'portfolio', 'attendance',
            ])->index();
            $table->string('source_id')->nullable();          // question_id / attempt_id / project_id …

            // --- what it demonstrates (semantic chain: NEVER an occupation here) ---
            $table->string('competency_id')->nullable()->index(); // canonical COMP-*
            $table->enum('kasba_dimension', [
                'KNOWLEDGE', 'ABILITY', 'SKILL', 'BEHAVIOUR', 'ATTITUDE',
            ])->nullable();                                    // inherited via ROLLS_UP_TO

            // --- the claim (levels, not percentages) ---
            $table->enum('performance_level', [
                'demonstrated', 'developing', 'emerging', 'insufficient',
            ])->nullable();
            $table->decimal('strength', 3, 2)->nullable();     // f(difficulty, DOK, recency, attempt_no)
            $table->decimal('reliability', 3, 2)->nullable();
            $table->string('validity_scope')->nullable();      // what this may/ may not claim

            // --- when (freshness/decay computed from observed_at) ---
            $table->timestamp('observed_at')->index();

            // --- graders / instruments ---
            $table->string('rater_id')->nullable();
            $table->string('assessment_id')->nullable();

            // --- trust & audit ---
            $table->boolean('verified')->default(false);       // assessment auto-true; prose false until confirmed
            $table->boolean('contested')->default(false);
            $table->uuid('superseded_by')->nullable();         // correction chain (append-only)
            $table->json('provenance')->nullable();            // {source_url?, method, ingested_by, adapter_version}

            $table->timestamp('created_at')->useCurrent();     // no updated_at — append-only

            $table->index(['student_id', 'competency_id']);
            $table->index(['student_id', 'kasba_dimension']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evidence_events');
    }
};
