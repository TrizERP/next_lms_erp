<?php

namespace App\Console\Commands\PAL;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Mirror config/pal_gamification.php's `badges` array into the `pal_badges`
 * catalogue table.
 *
 * The catalogue is DB-backed (BadgeService reads Badge::active(), not the
 * config), and was originally populated by
 * 2026_08_17_100000_create_pal_gamification_tables.php's own
 * syncBadgeCatalogue(). That runs once, so a badge ADDED to the config after
 * the migration has already run never reaches the table — this command is the
 * supported way to re-sync without editing or re-running a shipped migration.
 *
 * Idempotent, and deliberately identical in behaviour to the migration's
 * version: shipped columns are refreshed on every run, but `status` is never
 * touched, so an institute that retired a badge keeps it retired.
 */
class SyncBadgeCatalogueCommand extends Command
{
    protected $signature = 'pal:sync-badges {--dry-run : Report what would change without writing}';

    protected $description = 'Sync the PAL gamification badge catalogue from config into pal_badges';

    public function handle(): int
    {
        $badges = (array) config('pal_gamification.badges', []);
        if ($badges === []) {
            $this->warn('config/pal_gamification.php defines no badges — nothing to sync.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $inserted = $updated = 0;
        $order = 0;

        foreach ($badges as $badge) {
            $badgeId = (string) ($badge['badge_id'] ?? '');
            if ($badgeId === '') {
                continue;
            }

            $trigger = (array) ($badge['trigger'] ?? []);
            $payload = [
                'name' => (string) ($badge['name'] ?? $badgeId),
                'category' => (string) ($badge['category'] ?? 'mastery'),
                'description' => $badge['description'] ?? null,
                'student_message' => $badge['student_message'] ?? null,
                'hpc_domain' => $badge['hpc_domain'] ?? null,
                'casel_domain' => $badge['casel_domain'] ?? null,
                'ncdg_goal' => $badge['ncdg_goal'] ?? null,
                'rarity' => (string) ($badge['rarity'] ?? 'common'),
                'hpc_evidence_weight' => (float) ($badge['hpc_evidence_weight'] ?? 0),
                'scope' => (string) ($badge['scope'] ?? 'global'),
                'trigger_type' => (string) ($trigger['type'] ?? 'never'),
                'trigger_config' => json_encode($trigger),
                'challenge_mode_only' => (bool) ($badge['challenge_mode_only'] ?? false),
                'sort_order' => $order++,
                'updated_at' => now(),
            ];

            $exists = DB::table('pal_badges')->where('badge_id', $badgeId)->exists();

            if ($exists) {
                $updated++;
                if (! $dryRun) {
                    DB::table('pal_badges')->where('badge_id', $badgeId)->update($payload);
                }

                continue;
            }

            $inserted++;
            $this->line("  + {$badgeId} ({$payload['name']})");
            if (! $dryRun) {
                DB::table('pal_badges')->insert($payload + [
                    'badge_id' => $badgeId,
                    'status' => 'active',
                    'created_at' => now(),
                ]);
            }
        }

        $this->info(sprintf(
            '%s %d new badge(s), refreshed %d existing.',
            $dryRun ? 'Would insert' : 'Inserted',
            $inserted,
            $updated
        ));

        return self::SUCCESS;
    }
}
