<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Simulation Learning in Prayogshala: the simulations themselves, stored as data. Ported from hp_erp's g2g_simulation* tables (same columns,
 * same indexes under new names), with the tenancy of K12 instead of hp_erp's per-organisation gallery.
 *
 * A simulation is a typed specification (schema "g2g.sim/1" or "g2g.sim/2": the knobs, the model, the missions, the scene of one system the
 * learner operates) played by ONE generic runtime (public/sim-runtime in the front end). Nothing about a particular simulation lives in code.
 *
 *   lms_simulation           one row per simulation: the card (title, summary, category, minutes, cover) and its state. `sub_institute_id` is
 *                            the OWNING tenant: the platform tenant (config lms_content.platform_sub_institute_ids), since only it may author.
 *                            Other tenants read it through PrayogshalaService::visibleTenants(). hp_erp's lms_simulation_tenant (per-organisation
 *                            switch on/off) is not ported: tenant-1 sharing replaces it.
 *   lms_simulation_version   every saved version of the spec, immutable, with its sha256. A published simulation plays its `current_version`.
 *   lms_simulation_asset     the registry of the 3D models, images and HDR skies a spec names by sha256; the bytes live on the private
 *                            `digitalocean` disk under `k12_sim_assets/<sha256>.<ext>`. Licence and credit are shown to learners.
 *   lms_simulation_progress  where each learner is in each simulation: one row per (simulation, user).
 *
 * The link to curriculum concepts is lms_simulation_concept (next migration).
 *
 * `spec` is LONGTEXT, not json(): the application encodes and decodes it, as it does lab_config on lms_prayogshala_activity, and so a
 * MariaDB that predates native JSON cannot break it. No foreign keys: every read joins on ids and filters on the tenant.
 *
 * Idempotent: a table that already exists is left alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! $this->exists('lms_simulation')) {
            Schema::create('lms_simulation', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('sub_institute_id');                // the owning tenant (the platform tenant)
                $table->string('slug', 80);                                     // the spec's own id, stable across versions
                $table->string('title', 160);
                $table->string('subtitle', 160)->nullable();
                $table->text('summary')->nullable();
                $table->string('category', 60)->default('general');             // the filter chips of the gallery
                $table->string('tags', 255)->nullable();                        // comma separated
                $table->string('audience', 160)->nullable();
                $table->unsignedSmallInteger('minutes')->nullable();
                $table->string('difficulty', 20)->nullable();                   // intro | intermediate | advanced
                $table->string('cover_url', 500)->nullable();
                $table->string('status', 16)->default('draft');                // draft | published | archived
                $table->unsignedInteger('current_version')->default(0);
                $table->unsignedSmallInteger('missions')->default(0);           // read from the spec when it is saved, so a card needs no spec
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->unsignedBigInteger('deleted_by')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['sub_institute_id', 'status'], 'lmssim_tenant_status');
                $table->index(['sub_institute_id', 'slug'], 'lmssim_tenant_slug');
            });
        }

        if (! $this->exists('lms_simulation_version')) {
            Schema::create('lms_simulation_version', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('simulation_id');
                $table->unsignedInteger('version');
                $table->longText('spec');                                       // the canonical JSON text of the specification
                $table->char('sha256', 64);                                     // of exactly that text
                $table->string('note', 255)->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamp('created_at')->nullable();

                $table->unique(['simulation_id', 'version'], 'lmssimver_sim_version');
                $table->index(['simulation_id', 'sha256'], 'lmssimver_sim_sha');
            });
        }

        if (! $this->exists('lms_simulation_asset')) {
            Schema::create('lms_simulation_asset', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('sub_institute_id')->nullable();    // owning tenant; NULL = a platform asset any simulation may use
                $table->char('sha256', 64);                                     // of the file's bytes; how a specification names it
                $table->string('name', 120);
                $table->string('kind', 16)->default('model');                   // model | image | hdr
                $table->string('mime', 80)->default('model/gltf-binary');       // image/png, image/jpeg, image/webp or image/vnd.radiance for the others
                $table->unsignedInteger('bytes');
                $table->string('storage_path', 255);                            // on the disk; never sent to a browser
                $table->string('licence', 80)->nullable();
                $table->string('credit', 300)->nullable();
                $table->string('source_url', 500)->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['sub_institute_id', 'sha256'], 'lmssimast_tenant_sha');
                $table->index('sha256', 'lmssimast_sha');
            });
        }

        if (! $this->exists('lms_simulation_progress')) {
            Schema::create('lms_simulation_progress', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('sub_institute_id');                 // the LEARNER's own tenant
                $table->unsignedBigInteger('simulation_id');
                $table->unsignedInteger('version')->default(0);
                $table->unsignedBigInteger('user_id');
                $table->string('status', 16)->default('in_progress');           // in_progress | completed
                $table->unsignedSmallInteger('step')->default(0);
                $table->unsignedTinyInteger('percent')->default(0);
                $table->text('result')->nullable();                             // what the runtime reported (stars per mission), as JSON text
                $table->timestamp('started_at')->nullable();
                $table->timestamp('last_seen_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                $table->unique(['simulation_id', 'user_id'], 'lmssimprog_sim_user');
                $table->index(['sub_institute_id', 'user_id'], 'lmssimprog_tenant_user');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('lms_simulation_progress');
        Schema::dropIfExists('lms_simulation_asset');
        Schema::dropIfExists('lms_simulation_version');
        Schema::dropIfExists('lms_simulation');
    }

    private function exists(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (\Throwable) {
            return DB::select(
                'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1',
                [$table]
            ) !== [];
        }
    }
};
