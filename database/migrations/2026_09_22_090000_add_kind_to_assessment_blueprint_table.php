<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Splits blueprints into two kinds.
 *
 *  - `regular` — the marks-based paper design that already existed: sections,
 *    question types, marks by chapter, difficulty split.
 *  - `hpc` — a Holistic Progress Card design: no marks at all. Curricular
 *    goals, competencies and learning outcomes, judged against a proficiency
 *    scale by more than one assessor, evidenced by activities.
 *
 * They are the same OBJECT — a published design a school copies and adapts, on
 * the same lifecycle, with the same facets — and a completely different
 * `definition`. One table with a discriminator, rather than two tables, is what
 * keeps the list, the clone, the versioning and the tenant scoping single-copy;
 * only the definition normaliser and the editor branch.
 *
 * `total_marks` stays on the row and stays 0 for an HPC blueprint. Having no
 * marks is the whole point of an HPC, so the column is simply unused there
 * rather than repurposed into something that would read as a score.
 *
 * Existing rows are all marks-based, so the default backfills them correctly.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('assessment_blueprint') || Schema::hasColumn('assessment_blueprint', 'kind')) {
            return;
        }

        Schema::table('assessment_blueprint', function (Blueprint $table) {
            $table->string('kind', 20)->default('regular')->after('sub_institute_id');
            // The stage an HPC belongs to -- Foundational, Preparatory, Middle,
            // Secondary. Each has its own proficiency scale and its own set of
            // areas, so it is a facet a coordinator filters by, not a detail
            // buried in the JSON.
            $table->string('stage', 40)->nullable()->after('class_band');

            $table->index(['sub_institute_id', 'kind'], 'ab_tenant_kind');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('assessment_blueprint')) {
            return;
        }

        Schema::table('assessment_blueprint', function (Blueprint $table) {
            $table->dropIndex('ab_tenant_kind');
            $table->dropColumn(['kind', 'stage']);
        });
    }
};
