<?php

namespace App\Console\Commands\PAL;

use App\Models\PAL\MisconceptionCorrective;
use App\Models\PAL\MisconceptionLibrary;
use App\Services\PAL\Content\PalVocabulary;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Phase P3 — seed the misconception library from the curated data file.
 *
 * Idempotent: matches on (tag, sub_institute_id) and updates rather than
 * duplicating, so it is safe to re-run after an SME extends the data file.
 *
 * CONTENT LAW C6 is a hard gate here: an entry with no corrective is REJECTED,
 * not written. A library row that detects an error and has nothing to show for
 * it is worse than no row at all, so it never enters the table in the first place.
 *
 * Everything seeds as quality_status='draft'. These are spec examples; a human
 * approves them per tenant before any learner sees one (C4).
 */
class SeedMisconceptionsCommand extends Command
{
    protected $signature = 'pal:seed-misconceptions
        {--tenant=0 : sub_institute_id to seed into (0 = shared global vocabulary)}
        {--file= : override the data file path}
        {--link-concepts : resolve concept_code to a real lms_concept.id by name match}
        {--dry-run : validate and report, write nothing}';

    protected $description = 'PAL V4: seed the misconception library and its corrective content (spec §4.2)';

    public function handle(): int
    {
        $tenant = (int) $this->option('tenant');
        $dryRun = (bool) $this->option('dry-run');
        $path = $this->option('file') ?: database_path('data/pal_misconception_library.php');

        if (! file_exists($path)) {
            $this->error("Data file not found: {$path}");

            return self::FAILURE;
        }

        $entries = require $path;

        $scope = $tenant === 0 ? 'global' : 'tenant';

        $this->info('PAL V4 — seeding the misconception library');
        $this->line("source: {$path}");
        $this->line("target: sub_institute_id={$tenant} scope={$scope}" . ($dryRun ? '   [DRY RUN]' : ''));
        $this->line(str_repeat('─', 78));

        $stats = ['created' => 0, 'updated' => 0, 'correctives' => 0, 'rejected' => 0];
        $rejections = [];

        foreach ($entries as $entry) {
            $tag = $entry['tag'] ?? null;

            if (! $tag) {
                $rejections[] = ['(missing tag)', 'entry has no tag'];
                $stats['rejected']++;
                continue;
            }

            // CONTENT LAW C6 — no corrective, no row.
            if (empty($entry['correctives'])) {
                $rejections[] = [$tag, 'C6: no corrective content — rejected, not written'];
                $stats['rejected']++;
                continue;
            }

            $payload = [
                'tag' => $tag,
                'sub_institute_id' => $tenant,
                'scope' => $scope,
                'concept_code' => $entry['concept_code'] ?? null,
                'subject' => $entry['subject'] ?? null,
                'grade_band' => $entry['grade_band'] ?? null,
                'description' => $entry['description'] ?? '',
                'error_pattern' => $entry['error_pattern'] ?? null,
                'corrective_action' => $entry['corrective_action'] ?? null,
                'error_regex' => $entry['error_regex'] ?? null,
                'typical_wrong_answers' => $entry['typical_wrong_answers'] ?? [],
                'prevalence_rate' => $entry['prevalence_rate'] ?? null,
                'corrective_format' => $entry['corrective_format'] ?? null,
                'priority_level' => $entry['priority_level'] ?? 1,
                'quality_status' => 'draft',
                'tagged_by' => 'human',
                'teacher_confirmed' => false,
            ];

            $errors = PalVocabulary::validate($payload);
            if ($errors !== []) {
                $rejections[] = [$tag, implode('; ', $errors)];
                $stats['rejected']++;
                continue;
            }

            // A stored regex that does not compile would throw on every wrong
            // answer in production. Catch it here, not there.
            if ($payload['error_regex'] !== null) {
                if (@preg_match('/' . str_replace('/', '\/', $payload['error_regex']) . '/i', 'probe') === false) {
                    $rejections[] = [$tag, 'error_regex does not compile: ' . $payload['error_regex']];
                    $stats['rejected']++;
                    continue;
                }
            }

            if ($this->option('link-concepts')) {
                $payload['concept_ref_id'] = $this->resolveConcept($entry['concept_code'] ?? null, $tenant);
            }

            if ($dryRun) {
                $this->line("  <fg=green>ok</>  {$tag}  (" . count($entry['correctives']) . ' corrective'
                    . (count($entry['correctives']) === 1 ? '' : 's') . ')');
                $stats['created']++;
                $stats['correctives'] += count($entry['correctives']);
                continue;
            }

            DB::transaction(function () use ($payload, $entry, $tenant, $scope, &$stats) {
                $existing = MisconceptionLibrary::where('tag', $payload['tag'])
                    ->where('sub_institute_id', $tenant)
                    ->first();

                if ($existing) {
                    // Never downgrade an approved row back to draft — a re-run of
                    // the seeder must not undo a reviewer's approval.
                    unset($payload['quality_status']);
                    $existing->fill($payload)->save();
                    $misconception = $existing;
                    $stats['updated']++;
                } else {
                    $misconception = MisconceptionLibrary::create($payload);
                    $stats['created']++;
                }

                foreach ($entry['correctives'] as $c) {
                    $cPayload = [
                        'misconception_id' => $misconception->id,
                        'sub_institute_id' => $tenant,
                        'scope' => $scope,
                        'title' => $c['title'],
                        'body' => $c['body'] ?? null,
                        'media_url' => $c['media_url'] ?? null,
                        'format' => $c['format'] ?? 'text_diagram',
                        'h5p_type' => $c['h5p_type'] ?? null,
                        'language' => $c['language'] ?? config('pal_content.default_language'),
                        'estimated_duration_minutes' => $c['estimated_duration_minutes'] ?? null,
                        'priority_level' => $c['priority_level'] ?? 1,
                        'quality_status' => 'draft',
                        'tagged_by' => 'human',
                    ];

                    $existingC = MisconceptionCorrective::where('misconception_id', $misconception->id)
                        ->where('title', $c['title'])
                        ->where('sub_institute_id', $tenant)
                        ->first();

                    if ($existingC) {
                        unset($cPayload['quality_status']);
                        $existingC->fill($cPayload)->save();
                    } else {
                        MisconceptionCorrective::create($cPayload);
                    }

                    $stats['correctives']++;
                }
            });
        }

        $this->line('');
        $this->table(['Result', 'Count'], [
            ['Misconceptions created', $stats['created']],
            ['Misconceptions updated', $stats['updated']],
            ['Correctives written', $stats['correctives']],
            ['Rejected', $stats['rejected']],
        ]);

        if ($rejections !== []) {
            $this->error('REJECTED ENTRIES:');
            $this->table(['Tag', 'Reason'], $rejections);
        }

        $this->line('');
        $this->line('All rows seeded as quality_status=draft. Nothing reaches a learner until a human approves it (C4).');
        $this->line('Run `php artisan pal:vocab-check` to confirm the C6 gate.');

        return $stats['rejected'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Best-effort concept resolution. concept_code is spec vocabulary
     * (FRAC_ADD_UNLIKE); lms_concept stores human names ("Addition of unlike
     * fractions"). Match on the de-slugged words rather than guessing an id —
     * a wrong concept link routes correctives to the wrong learners, so an
     * unresolved NULL is the safer failure.
     */
    protected function resolveConcept(?string $code, int $tenant): ?int
    {
        if (! $code) {
            return null;
        }

        $words = array_filter(explode('_', strtolower($code)), fn ($w) => strlen($w) > 2);
        if ($words === []) {
            return null;
        }

        $q = DB::table('lms_concept');
        if ($tenant !== 0) {
            $q->where('sub_institute_id', $tenant);
        }

        foreach ($words as $w) {
            $q->where('name', 'like', '%' . $w . '%');
        }

        $matches = $q->limit(2)->pluck('id');

        // Ambiguous match is not a match.
        return $matches->count() === 1 ? (int) $matches->first() : null;
    }
}
