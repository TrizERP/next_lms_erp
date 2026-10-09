<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Prayogshala: one activity per topic, and a record of how each was generated.
 *
 * ONE PER TOPIC. `topic_slot` mirrors `topic_id` while the row is live and is NULL once the row is
 * soft-deleted (or has no topic). A unique index on (sub_institute_id, topic_slot) then gives the
 * database itself the rule "an institute has at most one live activity per topic": two concurrent
 * generations cannot both insert, a soft-deleted activity does not block its replacement, and
 * chapter-wide activities (no topic) are not constrained. MySQL and SQLite both allow many NULLs
 * in a unique index, so no partial index is needed.
 *
 * GENERATION TRACKING. `status` stays the publication state (draft | review | published).
 * `generation_status` is separate: it says whether the content exists at all.
 *   generating     a worker has the topic
 *   ready          lab content generated and validated
 *   failed         the provider or the validator rejected the attempt; safe to retry
 *   needs_content  the topic has too little source material to generate honestly
 * NULL means the row was authored by hand (or seeded) and has no generation record.
 *
 * Provenance - which topic text, concepts and chapter extraction an activity was built from, and a
 * hash of that material - lets a later run tell "the source changed" from "nothing to do".
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('lms_prayogshala_activity')) {
            return;
        }

        Schema::table('lms_prayogshala_activity', function (Blueprint $table) {
            if (! Schema::hasColumn('lms_prayogshala_activity', 'topic_slot')) {
                $table->unsignedBigInteger('topic_slot')->nullable()->after('topic_id');
                $table->string('generation_status', 16)->nullable()->after('status');
                $table->unsignedSmallInteger('generation_version')->default(0)->after('generation_status');
                $table->unsignedSmallInteger('generation_attempts')->default(0)->after('generation_version');
                $table->text('generation_error')->nullable()->after('generation_attempts');
                $table->string('generation_model', 80)->nullable()->after('generation_error');
                $table->timestamp('generated_at')->nullable()->after('generation_model');
                // sha256 of the source material the content was built from.
                $table->string('source_hash', 64)->nullable()->after('generated_at');
                // JSON: {extraction_ids:[], topic_id, concept_ids:[], excerpt_chars}
                $table->longText('source_refs')->nullable()->after('source_hash');
                // JSON list of lms_concept ids this one activity covers.
                $table->longText('concept_ids')->nullable()->after('source_refs');
            }
        });

        // Existing live topic-bound rows own their slot. A duplicate here would make the unique
        // index below fail, so only the oldest live row per (institute, topic) is given the slot;
        // any other stays chapter-visible but cannot collide. (None exist today.)
        $seen = [];
        foreach (DB::table('lms_prayogshala_activity')->whereNull('deleted_at')->whereNotNull('topic_id')->orderBy('id')->get(['id', 'sub_institute_id', 'topic_id']) as $row) {
            $key = $row->sub_institute_id . ':' . $row->topic_id;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            DB::table('lms_prayogshala_activity')->where('id', $row->id)->update(['topic_slot' => $row->topic_id]);
        }

        Schema::table('lms_prayogshala_activity', function (Blueprint $table) {
            $table->unique(['sub_institute_id', 'topic_slot'], 'uq_prayog_tenant_topic');
            $table->index(['sub_institute_id', 'generation_status'], 'idx_prayog_tenant_genstatus');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('lms_prayogshala_activity') || ! Schema::hasColumn('lms_prayogshala_activity', 'topic_slot')) {
            return;
        }

        Schema::table('lms_prayogshala_activity', function (Blueprint $table) {
            $table->dropUnique('uq_prayog_tenant_topic');
            $table->dropIndex('idx_prayog_tenant_genstatus');
        });
        Schema::table('lms_prayogshala_activity', function (Blueprint $table) {
            $table->dropColumn([
                'topic_slot', 'generation_status', 'generation_version', 'generation_attempts', 'generation_error',
                'generation_model', 'generated_at', 'source_hash', 'source_refs', 'concept_ids',
            ]);
        });
    }
};
