<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Support tables for the ported `CapabilityLibraryController` (G2G's
 * `App\Http\Controllers\Api\Competency\LibraryController` - the Skill /
 * Jobrole / Jobrole Task / Knowledge / Ability / Attitude / Behaviour /
 * Invisible library tabs + taxonomy editors).
 *
 * Of the eight `RESOURCES` tables that controller reads, `s_users_skills`,
 * `s_user_jobrole` and `s_user_jobrole_task` already exist in this target
 * (confirmed via codebase grep - other already-ported Competency Management
 * controllers use them directly). The remaining five below - plus the two
 * skill-detail-panel tables `skillAssociations()` joins - do not exist here
 * and have no tracked G2G migration either (they are legacy/pre-migration
 * tables in that schema, the same situation as `s_user_jobrole`). Their
 * shape is derived directly from the source controller's own field lists
 * and queries:
 *
 *   - s_user_knowledge / s_user_ability / s_user_attitude / s_user_behaviour
 *     the four KASA library tabs. Columns = LibraryController::RESOURCES's
 *     'fields' list for each type, plus the standard tenant/audit/soft-delete
 *     columns every 'tenant' => true, 'soft' => true resource in that config
 *     requires (baseQuery()/stamp()/destroyResource()).
 *   - s_invisible_library - the "Invisible Library" (mental models / frameworks
 *     / matrices) tab. Column count (18) and shape confirmed against G2G's own
 *     `2026_08_22_090000_align_s_invisible_library_with_dev_schema.php`, whose
 *     class doc states the dev schema has exactly 18 columns: the 10 RESOURCES
 *     fields (type, title, description, purpose, when_to_use, benefits,
 *     limitations, example_use_case, tags, difficulty_level) + id +
 *     sub_institute_id + 4 audit columns + created_at/updated_at/deleted_at.
 *     'tenant' => 'shared' or NULL sub_institute_id = shared platform content.
 *   - s_user_skill_application - schema ported EXACTLY from G2G's own
 *     `2025_06_20_084328_create_s_user_skill_application_table.php` (minus FK
 *     constraints, per this project's no-FK convention).
 *   - s_library_map - backs `LibraryController::libraryMapAttributes()`, which
 *     reads `{type}_ids` (knowledge_ids/ability_ids/attitude_ids/behaviour_ids)
 *     as comma-separated id lists keyed by (type, type_id, sub_institute_id).
 *     No G2G migration exists for this table either; shape derived from that
 *     method's own query.
 *
 * Same conventions as the rest of this module's migrations: bigIncrements,
 * indexed sub_institute_id, no FK constraints, audit columns, timestamps +
 * soft deletes where the source resource is 'soft' => true.
 */
return new class extends Migration
{
    private function kasaTable(string $name, array $fields): void
    {
        if (Schema::hasTable($name)) {
            return;
        }

        Schema::create($name, function (Blueprint $table) use ($fields) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('sub_institute_id')->index();
            $table->string('title', 191);
            $table->text('description')->nullable();
            $table->string('category', 191)->nullable()->index();
            $table->string('sub_category', 191)->nullable()->index();
            $table->text('business_link')->nullable();
            $table->string('assessment_method', 191)->nullable();

            foreach ($fields as $field) {
                $table->text($field)->nullable();
            }

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function up(): void
    {
        $this->kasaTable('s_user_knowledge', [
            'knowledge_tags', 'key_concepts', 'theoretical_foundation',
            'complexity_level', 'proficiency_expectation', 'references',
            'certification_options', 'compliance_relevance',
        ]);

        $this->kasaTable('s_user_ability', [
            'ability_tags', 'cognitive_elements', 'psychomotor_elements',
            'measurement_metrics', 'importance_level', 'common_challenges',
            'improvement_tips',
        ]);

        $this->kasaTable('s_user_attitude', [
            'attitude_tags', 'development_methods', 'negative_indicators',
            'improvement_strategies', 'cultural_alignment',
        ]);

        $this->kasaTable('s_user_behaviour', [
            'behaviour_tags', 'measurable_indicators', 'behaviour_alternatives',
            'performance_metrics', 'risk_implications', 'coaching_guidelines',
        ]);

        if (!Schema::hasTable('s_invisible_library')) {
            Schema::create('s_invisible_library', function (Blueprint $table) {
                $table->bigIncrements('id');
                // Nullable: NULL = shared platform-curated content ('tenant' =>
                // 'shared' in LibraryController::RESOURCES), a real id = that
                // tenant's own clone (see cloneInvisible()).
                $table->unsignedBigInteger('sub_institute_id')->nullable()->index();
                $table->string('type', 32);
                $table->string('title', 191);
                $table->text('description')->nullable();
                $table->text('purpose')->nullable();
                $table->text('when_to_use')->nullable();
                $table->text('benefits')->nullable();
                $table->text('limitations')->nullable();
                $table->text('example_use_case')->nullable();
                $table->string('tags', 500)->nullable();
                $table->string('difficulty_level', 32)->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->unsignedBigInteger('deleted_by')->nullable();
                $table->softDeletes();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('s_user_skill_application')) {
            Schema::create('s_user_skill_application', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('skill_id')->index();
                $table->string('proficiency_level')->index()->nullable();
                $table->text('application')->nullable();
                $table->unsignedBigInteger('sub_institute_id')->nullable()->index();
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->unsignedBigInteger('updated_by')->nullable()->index();
                $table->unsignedBigInteger('deleted_by')->nullable()->index();
                $table->softDeletes();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('s_library_map')) {
            Schema::create('s_library_map', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('sub_institute_id')->index();
                // 'jobrole' (only subject type this port reads/writes it for).
                $table->string('type', 32)->index();
                $table->unsignedBigInteger('type_id')->index();
                // Comma-separated id lists into s_user_{knowledge,ability,attitude,behaviour}.
                $table->text('knowledge_ids')->nullable();
                $table->text('ability_ids')->nullable();
                $table->text('attitude_ids')->nullable();
                $table->text('behaviour_ids')->nullable();
                $table->timestamps();

                $table->unique(['sub_institute_id', 'type', 'type_id'], 's_library_map_subject_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('s_library_map');
        Schema::dropIfExists('s_user_skill_application');
        Schema::dropIfExists('s_invisible_library');
        Schema::dropIfExists('s_user_behaviour');
        Schema::dropIfExists('s_user_attitude');
        Schema::dropIfExists('s_user_ability');
        Schema::dropIfExists('s_user_knowledge');
    }
};
