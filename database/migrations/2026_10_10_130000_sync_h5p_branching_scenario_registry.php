<?php

use App\Services\PAL\H5P\H5PModelRegistry;
use App\Services\PAL\H5P\H5PRegistrySeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Re-publish the H5P Model registry after Branching Scenario went native.
 *
 * The registry is the RUNTIME source of truth: H5PModelRegistry reads
 * `pal_vocabulary` and only falls back to config/pal_h5p.php when that table is
 * absent. Flipping `branching_scenario` from `planned` to `native` in the
 * config therefore changes nothing on a database that has already run an
 * earlier seed -- the hub card would not appear -- until this runs. It is the
 * same step the 2026-09-21 vertical's sync migration is.
 *
 * The idempotent seeder REFRESHES the `branching_scenario` row (it existed with
 * `implementation.status = planned`). Labels an admin edited are kept, tenant
 * rows are never touched, and no other type changes. The cache is flushed so a
 * long-running worker does not serve the old vocabulary for up to
 * PAL_H5P_REGISTRY_TTL seconds.
 *
 * `down()` does not prune: the seeder owns rows that predate this migration. A
 * native type whose table is missing is already reported as unavailable rather
 * than broken, so rolling back the tables is safe on its own.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(H5PRegistrySeeder::class)->sync();
        H5PModelRegistry::flush();
    }

    public function down(): void
    {
        H5PModelRegistry::flush();
    }
};
