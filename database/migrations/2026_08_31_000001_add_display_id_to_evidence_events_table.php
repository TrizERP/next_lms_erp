<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Additive, human-readable numeric identifier for evidence_events, alongside
 * (not replacing) the existing UUID evidence_id primary key.
 *
 * Per the evidence_id impact analysis: AssessmentEvidenceAdapter::
 * writeSubjectEvidence() needs a row's evidence_id known BEFORE the row is
 * inserted (it writes the new id into the prior row's superseded_by first,
 * then creates the new row) — only a pre-generated UUID supports that
 * ordering; an AUTO_INCREMENT primary key is only known after INSERT.
 * Rather than restructure that supersede-then-create sequencing, display_id
 * is a separate UNIQUE AUTO_INCREMENT column for reporting/display use
 * only — evidence_id stays the primary key and the sole identifier any
 * internal join, FK, or supersede logic uses.
 *
 * Raw DDL (not Blueprint) because MySQL requires an AUTO_INCREMENT column to
 * be indexed but does not require it to be the table's primary key — Laravel's
 * fluent bigIncrements()/autoIncrement() helpers assume/force PRIMARY, which
 * evidence_id already is.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            'ALTER TABLE evidence_events ADD COLUMN display_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT UNIQUE'
        );
    }

    public function down(): void
    {
        Schema::table('evidence_events', function ($table) {
            $table->dropColumn('display_id');
        });
    }
};
