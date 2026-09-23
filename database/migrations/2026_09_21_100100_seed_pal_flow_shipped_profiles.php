<?php

use App\Services\PAL\Flow\EsoFlowRegistry;
use App\Services\PAL\Flow\EsoFlowResolver;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Write the four shipped flow profiles into the database, once.
 *
 * ---------------------------------------------------------------------------
 * WHY THIS IS BEHAVIOUR-NEUTRAL
 * ---------------------------------------------------------------------------
 * It inserts profiles and publishes version 1 of each. It does NOT assign any
 * institute to any of them, so pal_flow_assignments stays empty and every
 * institute keeps resolving the default profile — `standard`, whose structure
 * is the hardcoded cascade transcribed. Nothing any learner is served changes
 * on the day this runs.
 *
 * The first moment behaviour changes anywhere is a deliberate
 * EsoFlowRegistry::assign() call, which is rollout step 8.
 *
 * ---------------------------------------------------------------------------
 * WHY THE STRUCTURE IS RESOLVED AND STORED WHOLE
 * ---------------------------------------------------------------------------
 * Each version stores the profile's FULLY RESOLVED structure — the shipped
 * catalogue with that profile's delta already applied and validated — rather
 * than the delta itself.
 *
 * config/pal_flow.php will change. A learner pinned to version 1 must keep
 * resolving version 1's flow after it does, and a stored delta would have to be
 * replayed against whatever the defaults happen to be at that point, which is
 * exactly what pinning exists to prevent.
 *
 * ---------------------------------------------------------------------------
 * WHY IT SWALLOWS ITS OWN FAILURE
 * ---------------------------------------------------------------------------
 * This estate carries 408 pending migrations against a shared database, and
 * they get run in bulk. A data migration that threw because the flow tables
 * were not present, or because a profile key had already been seeded by hand,
 * would abort that whole run and block hundreds of unrelated migrations behind
 * it.
 *
 * So it checks, logs and returns. The seeding is idempotent and re-runnable
 * through the registry at any time; a migration that halts an estate's catch-up
 * is a worse failure than one that has to be re-run.
 *
 * ---------------------------------------------------------------------------
 * ROLLBACK
 * ---------------------------------------------------------------------------
 * Deliberately a no-op, NOT a delete. Once a profile has been assigned or a
 * learner pinned to one of its versions, removing the row would strand them
 * with a flow_version_id pointing at nothing. Data this cheap to re-create is
 * not worth a destructive down().
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['pal_flow_profiles', 'pal_flow_profile_versions', 'pal_flow_assignments'] as $table) {
            if (! Schema::hasTable($table)) {
                Log::warning("pal_flow seed skipped: {$table} is not present on this connection.");

                return;
            }
        }

        try {
            $resolver = app(EsoFlowResolver::class);

            $outcome = app(EsoFlowRegistry::class)->seedShippedProfiles(
                static fn (string $key): array => $resolver->structureFor($key)
            );

            foreach ($outcome as $key => $what) {
                Log::info("pal_flow profile '{$key}': {$what}");
            }
        } catch (\Throwable $e) {
            // See "WHY IT SWALLOWS ITS OWN FAILURE" above.
            Log::error('pal_flow profile seed failed: ' . $e->getMessage());
        }
    }

    public function down(): void
    {
        // Intentionally empty. See the ROLLBACK note in the docblock.
    }
};
