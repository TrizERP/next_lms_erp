<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Course Master -> Concept Intelligence — tenant-wise tab names.
 *
 * The intelligence tab strip ships with a default name per dimension
 * (config/lms_concept_intelligence_tabs.php). An institute that calls
 * "Real World" something else stores only that one override here; every tab it
 * has not renamed keeps following the shipped default.
 *
 * Storing overrides rather than a full per-tenant copy is deliberate, and
 * matches pal_architecture_settings:
 *   - a deploy that revises a default reaches every tenant that has not
 *     overridden it, instead of leaving stale copies behind;
 *   - an untouched estate has zero rows here and still renders correctly;
 *   - "restore default" is a DELETE, not a re-seed.
 *
 * Scope is per institute. `sub_institute_id = 0` is the estate-wide fallback
 * consulted when a tenant has no row of its own — the same 0-default
 * convention the PAL overlay tables use. The API only ever writes a real
 * institute id; a scope-0 row is seeded deliberately, never by a tenant.
 *
 * Additive only: nothing existing is altered.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('lms_concept_intelligence_tab_labels')) {
            return;
        }

        Schema::create('lms_concept_intelligence_tab_labels', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('sub_institute_id')->default(0);

            // Tab slug — 'knowledge', 'realworld', 'dok', … Validated against
            // the config on write, so an unknown slug can never be stored. The
            // slug is never renamed; it keys the panel and the stored JSON.
            $table->string('tab_key', 64);

            // What this institute wants the tab to read. Only the label is
            // per-tenant; the dimension it names is fixed.
            $table->string('custom_label', 120);

            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            // A tenant holds at most one override per tab. sub_institute_id is
            // NOT NULL (0 = estate-wide), so this unique index actually bites —
            // a nullable column would let MySQL store duplicates.
            $table->unique(
                ['sub_institute_id', 'tab_key'],
                'lms_ci_tab_labels_scope_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lms_concept_intelligence_tab_labels');
    }
};
