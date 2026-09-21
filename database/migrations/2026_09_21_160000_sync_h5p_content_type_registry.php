<?php

use App\Services\PAL\H5P\H5PModelRegistry;
use App\Services\PAL\H5P\H5PRegistrySeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Re-publish the H5P Model registry after the 2026-09-21 vertical.
 *
 * The registry is the RUNTIME source of truth: H5PModelRegistry reads
 * `pal_vocabulary` and only falls back to config/pal_h5p.php when the table is
 * absent. Editing the config alone therefore registers four content types that
 * the hub, the inventory and the content adapter never see on any database
 * that has already run an earlier seed. This closes that gap, exactly as the
 * text-passage vertical's own sync migration did.
 *
 * On an existing install the idempotent seeder:
 *   - INSERTS `image_hotspots`, which had no registry row at all;
 *   - REFRESHES `memory_game`, `course_presentation` and `arithmetic_quiz`,
 *     whose rows existed with `implementation.status = planned` and are now
 *     `native` with a source table and a route;
 *   - REFRESHES the already-native types, which gain only the additive
 *     `module_category` key.
 *
 * `image_hotspot` -- the older Scenario type over h5p_scenarios -- is NOT
 * touched. It keeps its row, its route and its card.
 *
 * Labels an admin edited are kept and tenant rows are never touched; see the
 * seeder's header. The cache is flushed because a long-running worker would
 * otherwise serve the pre-migration vocabulary for up to
 * PAL_H5P_REGISTRY_TTL seconds.
 *
 * `down()` does NOT prune, for the reason the text-activity sync gives: the
 * seeder owns rows that predate this migration, and dropping them here would
 * take types this vertical never added. Rolling back leaves the registry
 * populated, which is harmless -- a native type whose table is missing is
 * already reported as unavailable rather than broken.
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
