<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Converts evidence_events.evidence_id from a UUID (CHAR(36)) primary key to
 * a BIGINT UNSIGNED AUTO_INCREMENT primary key, so evidence_id behaves like
 * every other table's numeric id — per explicit product decision, evidence_id
 * is to be the ONLY identifier (no separate display_id, no retained legacy
 * UUID audit column). superseded_by (a self-reference to another row's
 * evidence_id) is converted to the matching integer type in the same pass.
 *
 * Existing rows are assigned new sequential ids deterministically, ordered by
 * created_at (tie-broken by the old UUID for stability) via a window
 * function — not by whatever arbitrary physical row order MySQL/MariaDB
 * would otherwise backfill an AUTO_INCREMENT column in.
 *
 * IMPORTANT: this discards the original UUID values permanently — nothing in
 * this codebase was found to reference them externally (confirmed by a full
 * repo grep: no Neo4j reference, no other table's FK, frontend treats the id
 * as an opaque value it never persists), so this is a deliberate, one-way
 * decision, not an oversight. down() can restore the CHAR(36) column shape
 * but cannot recover the original UUID values — it generates fresh ones.
 *
 * AssessmentEvidenceAdapter::writeSubjectEvidence()/supersede() were updated
 * in the same change to create the row first (so the auto-increment id is
 * known) and then supersede the prior row, with an explicit
 * `evidence_id != $newEvidenceId` guard so the new row never marks itself
 * contested/superseded.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE evidence_events ADD COLUMN evidence_id_new BIGINT UNSIGNED NULL AFTER evidence_id');

        DB::statement('
            UPDATE evidence_events e
            JOIN (
                SELECT evidence_id, ROW_NUMBER() OVER (ORDER BY created_at, evidence_id) AS rn
                FROM evidence_events
            ) ranked ON ranked.evidence_id = e.evidence_id
            SET e.evidence_id_new = ranked.rn
        ');

        DB::statement('ALTER TABLE evidence_events ADD COLUMN superseded_by_new BIGINT UNSIGNED NULL AFTER superseded_by');

        DB::statement('
            UPDATE evidence_events e1
            JOIN evidence_events e2 ON e1.superseded_by = e2.evidence_id
            SET e1.superseded_by_new = e2.evidence_id_new
            WHERE e1.superseded_by IS NOT NULL
        ');

        DB::statement('ALTER TABLE evidence_events DROP PRIMARY KEY');
        DB::statement('ALTER TABLE evidence_events DROP COLUMN evidence_id');
        DB::statement('ALTER TABLE evidence_events DROP COLUMN superseded_by');

        DB::statement('ALTER TABLE evidence_events MODIFY evidence_id_new BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (evidence_id_new)');
        DB::statement('ALTER TABLE evidence_events CHANGE evidence_id_new evidence_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');

        DB::statement('ALTER TABLE evidence_events CHANGE superseded_by_new superseded_by BIGINT UNSIGNED NULL');
    }

    public function down(): void
    {
        // Schema-shape rollback only — original UUID values were dropped in
        // up() and cannot be recovered. Fresh UUIDs are generated so the
        // column is valid again; they will not match any pre-migration value.
        DB::statement('ALTER TABLE evidence_events DROP PRIMARY KEY');
        DB::statement('ALTER TABLE evidence_events CHANGE evidence_id evidence_id_old_int BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE evidence_events ADD COLUMN evidence_id CHAR(36) NULL');

        foreach (DB::table('evidence_events')->orderBy('evidence_id_old_int')->get() as $row) {
            DB::table('evidence_events')
                ->where('evidence_id_old_int', $row->evidence_id_old_int)
                ->update(['evidence_id' => (string) Str::uuid()]);
        }

        DB::statement('ALTER TABLE evidence_events MODIFY evidence_id CHAR(36) NOT NULL');
        DB::statement('ALTER TABLE evidence_events ADD PRIMARY KEY (evidence_id)');
        DB::statement('ALTER TABLE evidence_events DROP COLUMN evidence_id_old_int');

        DB::statement('ALTER TABLE evidence_events CHANGE superseded_by superseded_by_old_int BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE evidence_events ADD COLUMN superseded_by CHAR(36) NULL');
        DB::statement('ALTER TABLE evidence_events DROP COLUMN superseded_by_old_int');
    }
};
