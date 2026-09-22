<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A school's own HPC option lists.
 *
 * What a blueprint CONTAINS has been per school since assessment_blueprint
 * shipped: areas, curricular goals, competencies, the proficiency scale, the
 * abilities, all live in that school's own row and editing one school's copy
 * cannot touch another's.
 *
 * What a blueprint could CHOOSE FROM was not. Assessors, activity approaches,
 * evidence methods and the Part A elements were `const` arrays in
 * App\Domain\Exam\HpcBlueprint, identical everywhere, and HpcBlueprint's
 * normaliser silently dropped anything outside them -- so a school wanting a
 * "Grandparent" assessor, a "Community-based" activity or a "House points"
 * section on Part A had nowhere to put it. This table is where those go.
 *
 * FALLBACK IS PER OPTION TYPE, AND THAT IS THE WHOLE DESIGN. A school with no
 * rows for `assessor` gets the published list; the moment it saves one, its own
 * list replaces the published one for that type ONLY -- its activity approaches
 * stay on the default until it customises those too. Three consequences worth
 * having:
 *
 *   - nothing has to be seeded for 300+ tenants, and a new school works on day
 *     one with the NCERT vocabulary;
 *   - correcting or extending a published list in code reaches every school
 *     that has not overridden that type, without a data migration;
 *   - a school's customisation is visible as exactly the rows it chose to add,
 *     not as a full copy of the defaults it never looked at.
 *
 * `status = 0` retires an option without deleting it, so blueprints that
 * already reference it keep rendering while it disappears from the pickers.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('hpc_school_option')) {
            return;
        }

        Schema::create('hpc_school_option', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('sub_institute_id')->index();

            // assessor | activity_approach | evidence_mode | part_a_element
            $table->string('option_type', 40);
            // Stable machine name. A blueprint stores this, never the label, so
            // renaming "Parent / caregiver" to "Guardian" does not orphan every
            // blueprint that already selected it.
            $table->string('code', 60);
            $table->string('label', 191);
            $table->string('description', 500)->nullable();

            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('status')->default(true);
            // True where the school added this itself rather than keeping a
            // published option. Only used to tell the two apart on screen.
            $table->boolean('is_custom')->default(true);

            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['sub_institute_id', 'option_type', 'code'], 'hso_tenant_type_code');
            $table->index(['sub_institute_id', 'option_type', 'status'], 'hso_tenant_type_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hpc_school_option');
    }
};
