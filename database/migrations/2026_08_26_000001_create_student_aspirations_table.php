<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * student_aspirations
 * -------------------
 * The THIRD CAI input. ERP already has evidence (marks/PAL) and declared plan
 * (subject enrolment). It does NOT have "what does the student want to be?" —
 * this table is that. Without it CAI returns INSUFFICIENT_DATA, so populating it
 * (via the Grade-9 ambition form) is the binding constraint on the whole wedge.
 *
 * Aspiration is a SNAPSHOT SERIES (a student re-answers over time). We keep every
 * snapshot and flag the current one, so we can later show certainty rising/falling.
 * Never UPDATE a snapshot in place — insert a new row, flip is_current.
 *
 * Child data: capturing an occupation aspiration is fine. Verifiable parental
 * consent + retention are handled by the platform's consent layer, not here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_aspirations', function (Blueprint $table) {
            $table->id();

            $table->string('student_id')->index();
            $table->unsignedTinyInteger('grade');            // 9 or 10 for the wedge
            $table->string('academic_year', 9);              // e.g. "2026-2027"

            // --- Stated ambition (the student's own words + resolved occupation) ---
            $table->string('occupation_id')->nullable()      // canonical OCC-* ; null if free-text only
                  ->index();
            $table->string('occupation_name')->nullable();   // denormalised snapshot for display
            $table->string('expectation_age_30')->nullable(); // raw free-text answer, always kept

            $table->string('alternative_occupation_id')->nullable();
            $table->string('alternative_occupation_name')->nullable();

            // --- Certainty (OECD "career certainty") ---
            $table->decimal('certainty', 3, 2)->nullable();  // 0.00–1.00
            $table->text('certainty_reason')->nullable();    // "why?"

            // --- Parent aspiration (drives ERR_PARENT_CONFLICT) ---
            $table->string('parent_occupation_id')->nullable();
            $table->string('parent_occupation_name')->nullable();

            // --- Preferences (informational; feeds pathway/exploration) ---
            $table->string('preferred_stream')->nullable();
            $table->string('preferred_education_route')->nullable();

            // --- Provenance & lifecycle ---
            $table->enum('source', ['student_form', 'counsellor_entry', 'parent_form'])
                  ->default('student_form');
            $table->boolean('is_current')->default(true);    // latest snapshot for this student
            $table->timestamp('captured_at');

            $table->timestamps();

            // exactly one current snapshot per student is enforced in the repository,
            // but this index makes "get current aspiration" a single-row lookup.
            $table->index(['student_id', 'is_current']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_aspirations');
    }
};
