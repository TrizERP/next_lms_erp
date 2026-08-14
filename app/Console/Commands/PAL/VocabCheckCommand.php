<?php

namespace App\Console\Commands\PAL;

use App\Services\PAL\Content\PalVocabulary;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Phase P2 gate — every enum value in the DB validates against the closed
 * vocabularies, or is explicitly reported as drift.
 *
 * Read-only by default. This is the check that catches the 400-spellings-of-
 * "apply" failure mode early, while there are still hundreds of rows rather
 * than tens of thousands.
 */
class VocabCheckCommand extends Command
{
    protected $signature = 'pal:vocab-check
        {--tenant= : restrict to one sub_institute_id}
        {--show-rows=10 : sample row ids to print per violation}
        {--json : machine-readable output}';

    protected $description = 'PAL V4: validate every Content Intelligence enum value in the DB against config/pal_content.php (read-only)';

    /** table => [column => config key resolving to the allowed set] */
    protected array $checks = [
        'pal_question_metadata' => [
            'bloom_level' => 'bloom_levels',
            'knowledge_type' => 'knowledge_types',
            'content_type' => null,
            'format' => 'formats',
            'h5p_type' => 'h5p_types',
            'scaffold_type' => 'scaffold_types',
            'response_latency_band' => 'response_latency_bands',
            'guessing_vulnerability' => 'guessing_vulnerability',
            'assessment_type' => 'assessment_types',
            'language' => 'languages',
            'cultural_context' => 'cultural_contexts',
            'gender_representation' => 'gender_representation',
            'casel_domain' => 'casel_domains',
            'ngss_practice' => 'ngss_practices',
            'ncdg_goal' => 'ncdg_goals',
            'riasec_signal' => 'riasec_signals',
            'gardner_intelligence' => 'gardner_intelligences',
            'aptitude_domain' => 'aptitude_domains',
            'nep_vocational_stream' => 'nep_vocational_streams',
            'p21_skill' => 'p21_skills',
            'hpc_lens_primary' => 'hpc_lenses',
            'soft_skill_signal' => 'soft_skill_signals',
            'career_cluster_signal' => 'career_clusters',
            'quality_status' => 'quality_statuses',
            'tagged_by' => 'tagged_by',
            'scope' => 'scopes',
        ],
        'pal_content_metadata' => [
            'content_type' => 'content_types',
            'format' => 'formats',
            'bloom_level_served' => 'bloom_levels',
            'language' => 'languages',
            'cultural_context' => 'cultural_contexts',
            'h5p_type' => 'h5p_types',
            'casel_domain' => 'casel_domains',
            'ngss_practice' => 'ngss_practices',
            'ncdg_goal' => 'ncdg_goals',
            'riasec_signal' => 'riasec_signals',
            'hpc_lens_primary' => 'hpc_lenses',
            'quality_status' => 'quality_statuses',
            'tagged_by' => 'tagged_by',
            'scope' => 'scopes',
        ],
        'pal_concept_metadata' => [
            'bloom_ceiling' => 'bloom_levels',
            'hpc_lens' => 'hpc_lenses',
            'riasec_primary' => 'riasec_signals',
            'gardner_primary' => 'gardner_intelligences',
            'ngss_practice' => 'ngss_practices',
            'ncdg_goal' => 'ncdg_goals',
            'quality_status' => 'quality_statuses',
            'tagged_by' => 'tagged_by',
            'scope' => 'scopes',
        ],
        'pal_misconception_library' => [
            'corrective_format' => 'corrective_formats',
            'quality_status' => 'quality_statuses',
            'tagged_by' => 'tagged_by',
            'scope' => 'scopes',
        ],
        'pal_misconception_corrective' => [
            'format' => 'formats',
            'h5p_type' => 'h5p_types',
            'language' => 'languages',
            'quality_status' => 'quality_statuses',
            'tagged_by' => 'tagged_by',
            'scope' => 'scopes',
        ],
    ];

    public function handle(): int
    {
        $tenant = $this->option('tenant') !== null ? (int) $this->option('tenant') : null;
        $sample = (int) $this->option('show-rows');

        $violations = [];
        $checked = 0;

        foreach ($this->checks as $table => $columns) {
            if (! \Illuminate\Support\Facades\Schema::hasTable($table)) {
                $this->warn("skip {$table} — table does not exist (run the migration first)");
                continue;
            }

            foreach ($columns as $column => $configKey) {
                if ($configKey === null) {
                    continue;
                }

                $allowed = $this->allowedSet($configKey);
                if ($allowed === []) {
                    continue;
                }

                $checked++;

                $rows = DB::table($table)
                    ->when($tenant !== null, fn ($q) => $q->where('sub_institute_id', $tenant))
                    ->whereNotNull($column)
                    ->where($column, '!=', '')
                    ->whereNotIn($column, $allowed)
                    ->selectRaw("{$column} AS bad_value, COUNT(*) AS c, GROUP_CONCAT(id ORDER BY id LIMIT {$sample}) AS ids")
                    ->groupBy($column)
                    ->get();

                foreach ($rows as $r) {
                    $violations[] = [
                        'table' => $table,
                        'column' => $column,
                        'value' => $r->bad_value,
                        'rows' => (int) $r->c,
                        'sample_ids' => $r->ids,
                        'allowed' => $allowed,
                    ];
                }
            }
        }

        // Cross-field consistency: bloom_level and practice_level must agree.
        $mismatch = $this->bloomLadderMismatch($tenant);

        // Pedagogy ids must exist in the live LMS estate.
        $pedagogyDrift = $this->pedagogyDrift($tenant);

        // C5: nothing machine-written may sit in a human-only status.
        $c5 = $this->c5Violations($tenant);

        // C6: an approved misconception with no approved corrective.
        $c6 = $this->c6Violations($tenant);

        if ($this->option('json')) {
            $this->line(json_encode(compact('violations', 'mismatch', 'pedagogyDrift', 'c5', 'c6', 'checked'), JSON_PRETTY_PRINT));

            return $violations === [] && $mismatch === [] && $pedagogyDrift === [] && $c5 === [] && $c6 === []
                ? self::SUCCESS : self::FAILURE;
        }

        $this->info('PAL V4 — vocabulary check');
        $this->line("Checked {$checked} enum columns" . ($tenant !== null ? " for tenant {$tenant}" : ' across all tenants'));
        $this->line(str_repeat('─', 78));

        $failed = false;

        if ($violations === []) {
            $this->line('<fg=green>PASS</> — every enum value in the DB is registered in config/pal_content.php.');
        } else {
            $failed = true;
            $this->error('DRIFT — unregistered values found:');
            $this->table(
                ['Table', 'Column', 'Unregistered value', 'Rows', 'Sample ids'],
                array_map(fn ($v) => [$v['table'], $v['column'], $v['value'], $v['rows'], $v['sample_ids']], $violations)
            );
        }

        if ($mismatch !== []) {
            $failed = true;
            $this->error('BLOOM/LADDER MISMATCH — practice_level contradicts bloom_level:');
            $this->table(['Table', 'bloom_level', 'practice_level', 'Expected', 'Rows'], $mismatch);
        }

        if ($pedagogyDrift !== []) {
            $failed = true;
            $this->error('PEDAGOGY DRIFT — pedagogy_mapping_id values not present in '
                . config('pal_content.pedagogy_source.table') . ':');
            $this->table(['Table', 'pedagogy_mapping_id', 'Rows'], $pedagogyDrift);
        }

        if ($c5 !== []) {
            $failed = true;
            $this->error('C5 VIOLATION — machine-written rows sitting in a human-only status:');
            $this->table(['Table', 'quality_status', 'tagged_by', 'Rows'], $c5);
        }

        if ($c6 !== []) {
            $failed = true;
            $this->error('C6 VIOLATION — approved misconceptions with no approved corrective:');
            foreach ($c6 as $tag) {
                $this->line('  · ' . $tag);
            }
        }

        $this->line(str_repeat('─', 78));
        $this->line($failed ? '<fg=red>GATE: FAIL</>' : '<fg=green>GATE: PASS</>');

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /** Resolve a config key to a flat list of legal string values. */
    protected function allowedSet(string $key): array
    {
        $v = config("pal_content.{$key}", []);
        if ($v === []) {
            return [];
        }

        // Some sets are keyed maps (bloom_levels, formats, riasec_signals),
        // others are flat lists (languages, cultural_contexts).
        return array_is_list($v) ? array_values($v) : array_keys($v);
    }

    protected function bloomLadderMismatch(?int $tenant): array
    {
        $out = [];

        foreach ([
            'pal_question_metadata' => ['bloom_level', 'practice_level'],
            'pal_content_metadata' => ['bloom_level_served', 'practice_level'],
        ] as $table => [$bloomCol, $levelCol]) {
            if (! \Illuminate\Support\Facades\Schema::hasTable($table)) {
                continue;
            }

            $rows = DB::table($table)
                ->when($tenant !== null, fn ($q) => $q->where('sub_institute_id', $tenant))
                ->whereNotNull($bloomCol)
                ->whereNotNull($levelCol)
                ->selectRaw("{$bloomCol} AS bloom, {$levelCol} AS lvl, COUNT(*) AS c")
                ->groupBy($bloomCol, $levelCol)
                ->get();

            foreach ($rows as $r) {
                $expected = PalVocabulary::practiceLevelForBloom($r->bloom);
                if ($expected !== null && $expected !== (int) $r->lvl) {
                    $out[] = [$table, $r->bloom, (int) $r->lvl, $expected, (int) $r->c];
                }
            }
        }

        return $out;
    }

    protected function pedagogyDrift(?int $tenant): array
    {
        $live = array_keys(PalVocabulary::pedagogyOptions());
        $out = [];

        foreach (['pal_question_metadata', 'pal_content_metadata', 'pal_concept_metadata'] as $table) {
            if (! \Illuminate\Support\Facades\Schema::hasTable($table)) {
                continue;
            }

            $rows = DB::table($table)
                ->when($tenant !== null, fn ($q) => $q->where('sub_institute_id', $tenant))
                ->whereNotNull('pedagogy_mapping_id')
                ->when($live !== [], fn ($q) => $q->whereNotIn('pedagogy_mapping_id', $live))
                ->selectRaw('pedagogy_mapping_id, COUNT(*) AS c')
                ->groupBy('pedagogy_mapping_id')
                ->get();

            foreach ($rows as $r) {
                $out[] = [$table, $r->pedagogy_mapping_id, (int) $r->c];
            }
        }

        return $out;
    }

    protected function c5Violations(?int $tenant): array
    {
        $humanOnly = array_keys(array_filter(
            config('pal_content.quality_statuses', []),
            fn ($d) => $d['human_only'] === true
        ));

        $out = [];
        foreach (['pal_question_metadata', 'pal_content_metadata', 'pal_concept_metadata'] as $table) {
            if (! \Illuminate\Support\Facades\Schema::hasTable($table)) {
                continue;
            }

            $rows = DB::table($table)
                ->when($tenant !== null, fn ($q) => $q->where('sub_institute_id', $tenant))
                ->whereIn('quality_status', $humanOnly)
                ->whereIn('tagged_by', ['ai', 'derived'])
                ->selectRaw('quality_status, tagged_by, COUNT(*) AS c')
                ->groupBy('quality_status', 'tagged_by')
                ->get();

            foreach ($rows as $r) {
                $out[] = [$table, $r->quality_status, $r->tagged_by, (int) $r->c];
            }
        }

        return $out;
    }

    protected function c6Violations(?int $tenant): array
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('pal_misconception_library')) {
            return [];
        }

        $servable = config('pal_content.servable_statuses', ['approved']);

        return DB::table('pal_misconception_library as m')
            ->when($tenant !== null, fn ($q) => $q->where('m.sub_institute_id', $tenant))
            ->whereIn('m.quality_status', $servable)
            ->whereNotExists(function ($q) use ($servable) {
                $q->selectRaw(1)
                    ->from('pal_misconception_corrective as c')
                    ->whereColumn('c.misconception_id', 'm.id')
                    ->whereIn('c.quality_status', $servable);
            })
            ->pluck('m.tag')
            ->all();
    }
}
