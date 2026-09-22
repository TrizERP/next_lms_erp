<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PAL adaptive flow — profiles, their immutable versions, and who is assigned
 * what.
 *
 * A school's learning flow is DATA. This is where it lives.
 *
 * ---------------------------------------------------------------------------
 * WHY THREE TABLES AND NOT ONE
 * ---------------------------------------------------------------------------
 * Mirrors workflow_definitions + workflow_versions
 * (2026_08_20_000005_create_workflow_tables.php), whose docblock states the
 * invariant this needs almost word for word: a definition owns identity and
 * permissions, a VERSION owns the graph, "so a running instance keeps
 * executing the version it started on even after the definition is edited".
 * Swap "running instance" for "learner mid-concept" and it is the same problem.
 *
 * pal_architecture_settings — the per-institute config overlay this estate
 * already has — deliberately has NO version column, which means an
 * administrator editing it silently changes the rules for learners who are
 * part-way through. That is the defect these tables exist not to repeat.
 *
 * The assignment is a third table rather than a column on pal_flow_profiles
 * because the relationship is the wrong way round for a column: one profile
 * serves many institutes, and the whole point of the design is that 200 schools
 * share four profiles rather than owning one each.
 *
 * ---------------------------------------------------------------------------
 * WHY `definition` IS THE WHOLE STRUCTURE, NOT A DELTA
 * ---------------------------------------------------------------------------
 * pal_architecture_settings stores only what an administrator changed, so a
 * revised shipped default reaches every untouched tenant on deploy. That is
 * right for settings and WRONG here.
 *
 * A version must stay readable after config/pal_flow.php has moved underneath
 * it, because a learner pinned to version 3 must keep resolving version 3's
 * flow however the catalogue has since been revised. A delta would have to be
 * replayed against whatever the defaults happen to be today, which is exactly
 * the thing pinning exists to prevent. So a version carries the resolved
 * structure whole, and is immutable once active.
 *
 * ---------------------------------------------------------------------------
 * STATUS VOCABULARY
 * ---------------------------------------------------------------------------
 * draft | active | superseded, with a forward-only `superseded_by_id`, taken
 * from pal_curriculum_versions rather than workflow's
 * draft|published|archived. "Superseded" is the honest word for a flow that is
 * still governing pinned learners while no longer being assigned to anyone new
 * — "archived" would suggest nothing is running it.
 *
 * Immutability is enforced in EsoFlowRegistry::publish(), not by a database
 * trigger: this estate is forward-only (see ROLLBACK below) and a trigger could
 * not be removed if it turned out to be wrong.
 *
 * ---------------------------------------------------------------------------
 * ROLLBACK
 * ---------------------------------------------------------------------------
 * down() is correct but is DOCUMENTATION, not a safety net:
 * AppServiceProvider.php:76-90 registers a DB::listen that throws on any SQL
 * containing "DROP TABLE". Forward-only. Same wording as
 * 2026_09_16_100000_create_pal_learning_relations_table.php.
 *
 * Additive only: nothing existing is altered, and nothing in the engine reads
 * these tables until an assignment row exists. An estate that runs this
 * migration and stops there behaves exactly as it did before.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pal_flow_profiles')) {
            Schema::create('pal_flow_profiles', function (Blueprint $table) {
                $table->id();

                // 0 = an estate-wide profile any institute may be assigned.
                // A non-zero value is a profile authored for one school, which
                // the design discourages but does not forbid — see the
                // "profiles, not snowflakes" note in config/pal_flow.php.
                $table->unsignedBigInteger('sub_institute_id')->default(0);

                $table->string('profile_key', 64)
                    ->comment('standard | diagnostic_free | no_cfu | check_first');
                $table->string('label', 191);
                $table->text('description')->nullable();

                // pal_flow_profile_versions.id. No FK, matching the pal_*
                // sidecar convention; nullable because a profile exists before
                // its first version is published.
                $table->unsignedBigInteger('active_version_id')->nullable();

                // Exactly one row estate-wide carries this. It is what an
                // institute with no assignment resolves to, which is what makes
                // this whole migration behaviour-neutral on the day it runs.
                $table->boolean('is_default')->default(false);

                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->unique(['sub_institute_id', 'profile_key'], 'pfp_scope_key_unique');
                $table->index('active_version_id', 'pfp_active_version_idx');
                $table->index('is_default', 'pfp_default_idx');
            });
        }

        if (! Schema::hasTable('pal_flow_profile_versions')) {
            Schema::create('pal_flow_profile_versions', function (Blueprint $table) {
                $table->id();

                $table->unsignedBigInteger('profile_id')->comment('pal_flow_profiles.id');
                $table->unsignedInteger('version')->default(1);

                // draft      - being authored, never resolved for a learner
                // active     - assignable, and what new learners pin to
                // superseded - no longer assignable, still governing pinned learners
                $table->string('status', 16)->default('draft');

                // Forward-only pointer, per pal_curriculum_versions.
                $table->unsignedBigInteger('superseded_by_id')->nullable();

                // The WHOLE resolved structure: stages and phases, with ranks,
                // enabled flags and parameters. IMMUTABLE once status leaves
                // 'draft' — enforced in EsoFlowRegistry::publish().
                $table->json('definition');

                $table->text('change_note')->nullable();
                $table->unsignedBigInteger('published_by')->nullable();
                $table->timestamp('published_at')->nullable();
                $table->timestamps();

                $table->unique(['profile_id', 'version'], 'pfpv_profile_version_unique');
                $table->index('status', 'pfpv_status_idx');
            });
        }

        if (! Schema::hasTable('pal_flow_assignments')) {
            Schema::create('pal_flow_assignments', function (Blueprint $table) {
                $table->id();

                // An institute holds at most one assignment. NOT NULL with a 0
                // default so this unique index actually bites — a nullable
                // column would let MySQL store duplicates, the reasoning
                // 2026_08_14_160000 records at lines 56-58.
                $table->unsignedBigInteger('sub_institute_id')->default(0);

                $table->unsignedBigInteger('profile_id')->comment('pal_flow_profiles.id');
                $table->unsignedBigInteger('assigned_by')->nullable();
                $table->timestamps();

                $table->unique('sub_institute_id', 'pfa_tenant_unique');
                $table->index('profile_id', 'pfa_profile_idx');
            });
        }
    }

    public function down(): void
    {
        // Will throw under the DB::listen guard in AppServiceProvider. See the
        // ROLLBACK note in the docblock.
        Schema::dropIfExists('pal_flow_assignments');
        Schema::dropIfExists('pal_flow_profile_versions');
        Schema::dropIfExists('pal_flow_profiles');
    }
};
