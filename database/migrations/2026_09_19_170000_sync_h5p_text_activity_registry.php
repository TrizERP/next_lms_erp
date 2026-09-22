<?php

use App\Services\PAL\H5P\H5PModelRegistry;
use App\Services\PAL\H5P\H5PRegistrySeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Re-publish the H5P Model registry after the text-passage vertical.
 *
 * The registry is the RUNTIME source of truth -- H5PModelRegistry reads
 * `pal_vocabulary` and only falls back to config/pal_h5p.php when the table is
 * absent. So editing the config alone would register three content types that
 * the hub, the inventory and the content adapter never see on any database
 * that has already run the 2026_08_14 seed. This migration closes that gap.
 *
 * It re-runs the same idempotent seeder, which on an existing install:
 *   - INSERTS `drag_text`, which had no registry row at all;
 *   - REFRESHES `fill_in_the_blanks` and `mark_the_words`, whose rows existed
 *     with `implementation.status = planned` and are now `native` with a
 *     source table, a discriminator and a route.
 *
 * Labels an admin edited are kept and tenant rows are never touched -- see the
 * seeder's header. The registry cache is flushed afterwards because a
 * long-running worker would otherwise serve the pre-migration vocabulary for
 * up to PAL_H5P_REGISTRY_TTL seconds.
 *
 * `down()` does NOT prune. Pruning removes the rows the seeder owns, which
 * would take the six §8.1 types the 2026_08_14 migration added with it -- that
 * migration's own down() is where that belongs. Rolling this one back leaves
 * the registry populated, which is harmless: the tables are dropped by the
 * migration beside this one, and a native type whose table is missing is
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
