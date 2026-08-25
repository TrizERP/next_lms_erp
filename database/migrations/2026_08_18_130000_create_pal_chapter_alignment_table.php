<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chapter alignment - the bridge without which the coherence map has a spine
 * and nothing hanging off it.
 *
 * ---------------------------------------------------------------------------
 * THE PROBLEM THIS TABLE EXISTS FOR (measured 2026-08-18)
 * ---------------------------------------------------------------------------
 * Every subject in this estate carries TWO chapter vocabularies for the same
 * real syllabus, and they share no ids:
 *
 *   Class 9 Maths  concepts on chapters 8594-8603 ("The World of Numbers")
 *                  content  on chapters  934-948  ("NUMBER SYSTEMS")
 *                  questions on           935, 8560
 *
 *   Class 7 Maths  content  on 8038-8045 (96 rows) AND on 137/140/145/4624-4635 (243 rows)
 *                  questions on 4624-4635 etc (490 rows), 8038 (2 rows)
 *
 * The newer set is what the AI extraction wrote concepts against; the older set
 * is where years of authored content and the entire question bank actually
 * live. Joining on chapter_id therefore returns nothing, which is exactly what
 * `pal:coherence-tag --dry-run` reports: 321 of 321 content rows and 26 of 26
 * questions land on a chapter with no concepts.
 *
 * Aligning the two by NAME is what makes the existing estate reachable from the
 * new concept layer. "POLYNOMIALS" -> "Introduction to Linear Polynomials" is
 * obvious to a person and invisible to a foreign key.
 *
 * ---------------------------------------------------------------------------
 * WHY A TABLE AND NOT AN INFERENCE AT QUERY TIME
 * ---------------------------------------------------------------------------
 * A name match is a JUDGEMENT. Recomputing it per request would make the
 * recommendation non-reproducible the moment a title is edited, and would give
 * nobody a place to correct a wrong pairing. Persisting it makes the mapping
 * reviewable, auditable and stable - `status` is how a human overrules the
 * matcher, and nothing with status='rejected' is ever used.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pal_chapter_alignment')) {
            Schema::create('pal_chapter_alignment', function (Blueprint $table) {
                $table->id();

                // The chapter the CONTENT/QUESTIONS point at (the older set).
                // Deliberately not a foreign key: these ids exist in neither
                // chapter_master nor any other table - they survive only as
                // :Chapter nodes in Neo4j and in the 2026-08-10 rescue CSV.
                $table->unsignedBigInteger('estate_chapter_id');
                $table->string('estate_chapter_name', 255)->nullable();

                // The chapter the CONCEPTS were extracted against (the newer
                // set). This one does exist in chapter_master.
                $table->unsignedBigInteger('concept_chapter_id');
                $table->string('concept_chapter_name', 255)->nullable();

                $table->unsignedBigInteger('sub_institute_id')->default(0);
                $table->unsignedBigInteger('standard_id');
                $table->unsignedBigInteger('subject_id');

                $table->double('confidence')->default(0);
                $table->string('matched_by', 16)->default('name')->comment('name | manual');
                // proposed | approved | rejected. Only 'approved' and
                // 'proposed' are consumed; 'rejected' is a permanent veto that
                // a re-run must not resurrect.
                $table->string('status', 16)->default('proposed');
                $table->unsignedBigInteger('reviewed_by')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();

                $table->unique(['estate_chapter_id', 'concept_chapter_id', 'sub_institute_id'], 'pca_pair_unique');
                $table->index(['sub_institute_id', 'standard_id', 'subject_id'], 'pca_scope_idx');
                $table->index(['estate_chapter_id', 'status'], 'pca_lookup_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pal_chapter_alignment');
    }
};
