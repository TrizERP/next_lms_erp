<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Purely additive index supporting the (student_id, competency_id, grade)
 * evidence uniqueness key AssessmentEvidenceAdapter::supersede() now scopes
 * by — no column changes, no data migration, no data loss. The existing
 * (student_id, competency_id) index stays; this is a separate, wider index
 * for the new grade-scoped lookup pattern.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evidence_events', function (Blueprint $table) {
            $table->index(['student_id', 'competency_id', 'grade'], 'evidence_events_student_competency_grade_index');
        });
    }

    public function down(): void
    {
        Schema::table('evidence_events', function (Blueprint $table) {
            $table->dropIndex('evidence_events_student_competency_grade_index');
        });
    }
};
