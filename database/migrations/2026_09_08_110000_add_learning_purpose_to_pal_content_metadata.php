<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds `learning_purpose` to pal_content_metadata.
 *
 * What a content object IS (content_type) and what FORM it takes (format) were
 * already modelled. What it is FOR was not, so the only question PAL's
 * Corrective Micro-Lesson step could ask was "what else exists on this
 * concept?" — which is how a learner who has just failed a question can be
 * handed the assessment item they failed, or an enrichment activity, as their
 * remediation.
 *
 * Nullable, and deliberately not backfilled. Every existing row predates the
 * vocabulary, so any value written here now would be a guess at an author's
 * intent; NULL says "not yet classified", which is true and is what a coverage
 * report should show. The closed set lives in config/pal_content.php
 * (`learning_purposes`) and is enforced by PalVocabulary::validate(), the same
 * way every other PAL vocabulary is.
 *
 * Indexed with concept_ref_id because the query this exists to serve is
 * "corrective content for THIS concept", never purpose across the whole estate.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pal_content_metadata')) {
            return;
        }

        if (Schema::hasColumn('pal_content_metadata', 'learning_purpose')) {
            return;
        }

        Schema::table('pal_content_metadata', function (Blueprint $table) {
            $table->string('learning_purpose', 32)
                ->nullable()
                ->after('content_type');

            $table->index(['concept_ref_id', 'learning_purpose'], 'pal_content_purpose_idx');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('pal_content_metadata')) {
            return;
        }

        if (! Schema::hasColumn('pal_content_metadata', 'learning_purpose')) {
            return;
        }

        Schema::table('pal_content_metadata', function (Blueprint $table) {
            $table->dropIndex('pal_content_purpose_idx');
            $table->dropColumn('learning_purpose');
        });
    }
};
