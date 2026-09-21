<?php

use App\Services\PAL\H5P\H5PModelRegistry;
use App\Services\PAL\H5P\H5PRegistrySeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Re-publish the H5P Model registry after Single Choice Set and True/False.
 *
 * The registry is the RUNTIME source of truth: H5PModelRegistry reads
 * `pal_vocabulary` and falls back to config/pal_h5p.php only when that table
 * is absent. Editing the config alone therefore registers two content types
 * that the hub, the inventory and the content adapter never see on any
 * database that has already run an earlier seed -- which is every environment
 * except a brand new one. This closes that gap, exactly as the two verticals
 * before it did.
 *
 * On an existing install the idempotent seeder INSERTS `single_choice_set`
 * and `true_false`, neither of which had a registry row in any form. Nothing
 * else changes: no existing type is promoted, retagged or reordered by this
 * migration.
 *
 * Labels an admin edited are kept and tenant rows are never touched; see the
 * seeder's header. The cache is flushed because a long-running worker would
 * otherwise serve the pre-migration vocabulary for up to
 * PAL_H5P_REGISTRY_TTL seconds -- which on a hub page looks exactly like the
 * new cards having failed to ship.
 *
 * `down()` does NOT prune, for the reason both earlier syncs give: the seeder
 * owns rows that predate this migration, and dropping them here would take
 * types this vertical never added. Rolling back leaves the registry populated,
 * which is harmless -- a native type whose table is missing is already
 * reported as unavailable rather than broken.
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
