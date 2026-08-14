<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PAL V4 Pedagogy Engine reference tables.
 *
 * The Pedagogy Engine rule set (PAL_V4_AI_Pedagogy_Engine) is codified data, not
 * learner data: tiered IF/THEN rules, the engagement-score composition, and the
 * pedagogy x trigger map. It is stored here so the PAL UI can be served entirely
 * from the backend API instead of static frontend objects.
 *
 * Two tables rather than one per section: every section is a list of rows with
 * the same shape (condition -> action -> pedagogy -> h5p -> trigger), so section
 * specific extras (signal weights, band ranges, avoid lists) live in `rule_meta`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pal_pedagogy_engine_sections', function (Blueprint $table) {
            $table->id();
            $table->string('section_key')->unique(); // tier-1, engagement-score, trigger-map, ...
            $table->string('name');
            $table->string('subtitle')->nullable();
            $table->text('summary')->nullable();
            $table->string('section_type')->default('rules'); // rules | signals | triggers
            $table->json('badges')->nullable();
            $table->string('implementation_status')->nullable(); // Not Implemented | Partially Implemented | Implemented
            $table->text('current_state')->nullable();           // what the product does today
            $table->text('gap')->nullable();                     // what is still missing
            $table->boolean('ui_visible')->default(true);        // hidden sections stay API reachable
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'ui_visible', 'sort_order'], 'pal_pedagogy_section_listing_idx');
        });

        Schema::create('pal_pedagogy_engine_rules', function (Blueprint $table) {
            $table->id();
            $table->string('section_key');
            $table->string('rule_key');
            $table->string('group_label')->nullable(); // rule | signal | band | trigger
            $table->text('condition');
            $table->text('action')->nullable();
            $table->string('pedagogy')->nullable();   // pedagogy tag exactly as the rule set defines it
            $table->string('h5p_type')->nullable();
            $table->string('scaffolding')->nullable();
            $table->text('trigger_action')->nullable();
            $table->text('reason')->nullable();
            $table->string('implementation_status')->nullable();
            // Named `rule_meta`, not `attributes`, because `attributes` collides
            // with Eloquent's internal Model::$attributes bag.
            $table->json('rule_meta')->nullable();    // weight, range, avoid, do_not, h5p_priority, duration
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['section_key', 'rule_key'], 'pal_pedagogy_rule_key_unique');
            $table->index(['section_key', 'is_active', 'sort_order'], 'pal_pedagogy_rule_listing_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pal_pedagogy_engine_rules');
        Schema::dropIfExists('pal_pedagogy_engine_sections');
    }
};
