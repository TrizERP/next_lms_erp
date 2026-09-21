<?php

namespace App\Console\Commands;

use App\Services\Remap\ChapterCorpusBuilder;
use App\Services\Remap\LegacyProfileBuilder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Validates the authored crosswalk and loads it into
 * lms_chapter_crosswalk, which is the only source of chapter targets
 * the apply path will read.
 *
 * Validation is strict and total: every discovered legacy group must
 * have exactly one entry, every target must be a live std-10 chapter in
 * the right tenant, and every entry must carry a written rationale.
 * Anything short of that aborts without writing.
 */
class RemapLoadCrosswalk extends Command
{
    protected $signature = 'remap:load-crosswalk
                            {--apply : write to lms_chapter_crosswalk (default is validate only)}
                            {--run= : reuse an existing run id}';

    protected $description = 'Validate database/remap/crosswalk_std10.php and load it into lms_chapter_crosswalk';

    public function handle(): int
    {
        $scope = config('remap.scope');
        $path  = base_path(config('remap.crosswalk_file'));

        if (!is_file($path)) {
            $this->error("crosswalk file not found: {$path}");
            return self::FAILURE;
        }

        $entries = require $path;
        $groups  = (new LegacyProfileBuilder())->groups();
        $corpus  = (new ChapterCorpusBuilder())->build();

        $problems = $this->validate($entries, $groups, $corpus, $scope);

        if ($problems) {
            $this->error('crosswalk validation failed:');
            foreach ($problems as $p) {
                $this->line('  - ' . $p);
            }
            return self::FAILURE;
        }

        $this->info(sprintf('crosswalk valid: %d entries covering all %d legacy groups', count($entries), count($groups)));
        $this->summarise($entries, $groups, $corpus);

        if (!$this->option('apply')) {
            $this->newLine();
            $this->warn('validate-only. re-run with --apply to load into lms_chapter_crosswalk.');
            return self::SUCCESS;
        }

        $runId = $this->option('run') ?: (string) Str::uuid();
        $this->loadRows($entries, $groups, $corpus, $scope, $runId);

        $this->newLine();
        $this->info("loaded into lms_chapter_crosswalk under run {$runId}");
        $this->line("next: php artisan remap:snapshot --run={$runId}");

        return self::SUCCESS;
    }

    /**
     * @return array<int,string>
     */
    private function validate(array $entries, array $groups, array $corpus, array $scope): array
    {
        $problems = [];
        $seen     = [];

        foreach ($entries as $i => $e) {
            foreach (['subject', 'chapter', 'decision', 'why'] as $key) {
                if (!array_key_exists($key, $e)) {
                    $problems[] = "entry #{$i}: missing '{$key}'";
                    continue 2;
                }
            }

            $id = "{$e['subject']}/{$e['chapter']}";

            if (isset($seen[$id])) {
                $problems[] = "duplicate entry for {$id}";
            }
            $seen[$id] = true;

            if (trim((string) $e['why']) === '') {
                $problems[] = "{$id}: empty rationale";
            }

            if (!in_array($e['decision'], ['map', 'nearest_surviving', 'out_of_syllabus', 'needs_review'], true)) {
                $problems[] = "{$id}: unknown decision '{$e['decision']}'";
                continue;
            }

            $wantsTarget = in_array($e['decision'], ['map', 'nearest_surviving'], true);
            $target      = $e['to'] ?? null;

            if ($wantsTarget) {
                if (!$target) {
                    $problems[] = "{$id}: {$e['decision']} without a target chapter";
                    continue;
                }

                $chapter = $corpus[$target] ?? null;

                if (!$chapter) {
                    $problems[] = "{$id}: target {$target} is not a live std-10 chapter";
                } elseif ((int) $chapter['standard_id'] !== (int) $scope['standard_id']) {
                    $problems[] = "{$id}: target {$target} is standard {$chapter['standard_id']}";
                } elseif ((int) $chapter['sub_institute_id'] !== (int) $scope['sub_institute_id']) {
                    $problems[] = "{$id}: target {$target} belongs to tenant {$chapter['sub_institute_id']}";
                }
            } elseif ($target) {
                $problems[] = "{$id}: {$e['decision']} must not carry a target";
            }
        }

        // Completeness in both directions: no group left undecided, and
        // no decision for a group that does not exist.
        $groupKeys = array_map(fn ($g) => "{$g['subject_id']}/{$g['chapter_id']}", $groups);

        foreach (array_diff($groupKeys, array_keys($seen)) as $missing) {
            $problems[] = "legacy group {$missing} has no crosswalk entry";
        }
        foreach (array_diff(array_keys($seen), $groupKeys) as $extra) {
            $problems[] = "crosswalk entry {$extra} matches no legacy group";
        }

        return $problems;
    }

    private function summarise(array $entries, array $groups, array $corpus): void
    {
        $byKey = [];
        foreach ($entries as $e) {
            $byKey["{$e['subject']}/{$e['chapter']}"] = $e;
        }

        $decisions = [];
        $mapped    = ['questions' => 0, 'content' => 0, 'teacher' => 0];
        $held      = ['questions' => 0, 'content' => 0, 'teacher' => 0];
        $cross     = 0;

        foreach ($groups as $g) {
            $e = $byKey["{$g['subject_id']}/{$g['chapter_id']}"];
            $decisions[$e['decision']] = ($decisions[$e['decision']] ?? 0) + 1;

            $isMapped = in_array($e['decision'], ['map', 'nearest_surviving'], true);

            if ($isMapped && (int) ($corpus[$e['to']]['subject_id'] ?? 0) !== (int) $g['subject_id']) {
                $cross++;
            }

            foreach (['questions', 'content', 'teacher'] as $class) {
                $isMapped ? $mapped[$class] += $g[$class] : $held[$class] += $g[$class];
            }
        }

        $this->newLine();
        $this->line('decisions: ' . implode('  ', array_map(
            fn ($k, $v) => "{$k}={$v}",
            array_keys($decisions),
            $decisions
        )));

        $this->table(
            ['', 'Q&A', 'CR', 'TW'],
            [
                ['will be remapped', $mapped['questions'], $mapped['content'], $mapped['teacher']],
                ['held for review', $held['questions'], $held['content'], $held['teacher']],
                ['coverage', $this->pct($mapped['questions'], $held['questions']),
                             $this->pct($mapped['content'], $held['content']),
                             $this->pct($mapped['teacher'], $held['teacher'])],
            ]
        );

        if ($cross) {
            $this->line("cross-subject moves: {$cross} group(s)");
        }
    }

    private function pct(int $a, int $b): string
    {
        $total = $a + $b;
        return $total === 0 ? '-' : round(100 * $a / $total) . '%';
    }

    private function loadRows(array $entries, array $groups, array $corpus, array $scope, string $runId): void
    {
        $counts = [];
        foreach ($groups as $g) {
            $counts["{$g['subject_id']}/{$g['chapter_id']}"] = $g;
        }

        DB::table('lms_remap_run')->updateOrInsert(
            ['run_id' => $runId],
            [
                'command'    => 'remap:load-crosswalk',
                'args_json'  => json_encode(['file' => config('remap.crosswalk_file')]),
                'status'     => 'running',
                'started_at' => now(),
            ]
        );

        $rows = [];

        foreach ($entries as $e) {
            $key   = "{$e['subject']}/{$e['chapter']}";
            $group = $counts[$key];
            $to    = $e['to'] ?? null;

            $newSubject = $to ? (int) $corpus[$to]['subject_id'] : null;

            // approved_auto vs low_confidence is a reporting band, not a
            // gate: everything mapped is applied, but the weaker calls
            // are labelled so `remap:rollback --review-status=
            // low_confidence` can undo exactly that tranche.
            $reviewStatus = match ($e['decision']) {
                // out_of_syllabus is an ACTIONABLE decision (soft-delete
                // the questions), not an absence of one, so it bands on
                // confidence like a mapping does.
                'map', 'nearest_surviving', 'out_of_syllabus' => ($e['conf'] ?? 0) >= config('remap.thresholds.auto_apply', 0.70)
                    ? 'approved_auto'
                    : 'low_confidence',
                default => 'needs_review',
            };

            $rows[] = [
                'run_id'            => $runId,
                'sub_institute_id'  => $scope['sub_institute_id'],
                'standard_id'       => $scope['standard_id'],
                'legacy_subject_id' => $e['subject'],
                'legacy_chapter_id' => $e['chapter'],
                'new_subject_id'    => $newSubject,
                'new_chapter_id'    => $to,
                'decision'          => $e['decision'],
                'mapping_kind'      => $to === null
                    ? null
                    : ($newSubject !== (int) $e['subject'] ? 'cross_subject' : ($e['decision'] === 'nearest_surviving' ? 'nearest_surviving' : 'exact')),
                'review_status'     => $reviewStatus,
                'confidence'        => $e['conf'] ?? null,
                'evidence_json'     => json_encode(['why' => $e['why'], 'source' => 'authored'], JSON_UNESCAPED_UNICODE),
                'row_counts_json'   => json_encode([
                    'questions' => $group['questions'],
                    'content'   => $group['content'],
                    'teacher'   => $group['teacher'],
                ]),
                'shortlist_mode'    => 'authored',
                'created_at'        => now(),
            ];
        }

        DB::table('lms_chapter_crosswalk')->where('run_id', $runId)->delete();

        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('lms_chapter_crosswalk')->insert($chunk);
        }

        DB::table('lms_remap_run')->where('run_id', $runId)->update([
            'counts_json' => json_encode(['crosswalk_rows' => count($rows)]),
        ]);
    }
}
