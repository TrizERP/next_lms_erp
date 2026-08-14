<?php

use App\Services\PAL\H5P\H5PRegistrySeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * PAL V4 — H5P Model registry.
 *
 * Publishes the H5P Model (PAL_V4_Pedagogy_Frameworks_H5P.md) into
 * `pal_vocabulary`, the closed-vocabulary registry that already holds
 * h5p_types / casel_domains / ngss_practices / ncdg_goals / gardner /
 * riasec / hpc_lenses. Nothing new is invented where a domain already
 * exists — existing rows are ENRICHED in place (label kept, metadata and
 * description filled in) and only genuinely missing codes are inserted.
 *
 * What this adds on top of what was already there:
 *   - the 6 H5P types §8.1 lists that the registry did not have
 *     (crossword, summary, audio_recorder, arithmetic_quiz,
 *      find_the_hotspot, image_sequencing) → 21 total
 *   - `metadata` on every H5P type: xAPI events, Bloom range, fluency
 *     trackability, engagement weight, and the `implementation` block that
 *     binds the abstract type to this ERP's real H5P tables
 *   - domain `pedagogy_tags` — the 12 PAL V4 pedagogies, each carrying its
 *     primary/secondary H5P types, HPC rubric and the §9 coverage matrix
 *   - domains `music_domains`, `sports_domains`, `finance_levels`,
 *     `xapi_verbs`, `engagement_signals`, `pedagogy_selection_rules`
 *   - NCDG CM4, which §4.2 requires and the registry was missing
 *
 * The work itself lives in H5PRegistrySeeder so it can be re-run whenever
 * config/pal_h5p.php changes, without a new migration each time:
 *
 *     php artisan pal:h5p-registry-sync
 */
return new class extends Migration
{
    public function up(): void
    {
        app(H5PRegistrySeeder::class)->sync();
    }

    public function down(): void
    {
        app(H5PRegistrySeeder::class)->prune();
    }
};
