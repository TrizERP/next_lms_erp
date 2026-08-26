<?php

namespace App\Console\Commands\PAL;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Derives pal_competencies from real exam evidence, so PAL Intelligence stops
 * reporting the same figures for every learner.
 *
 * WHY THIS EXISTS
 * ---------------
 * Almost every number on the PAL Intelligence screen traces back to one table.
 * LearnerStateEngine::inferCompetency() reads pal_competencies for mastery,
 * Bloom level and knowledge gaps, and the whole of LearningVelocityEngine -
 * velocity, plateau, regression, concepts mastered, trend - queries nothing
 * else. That table held 4 rows, so every metric fell through to its default.
 *
 * THE GRAIN, AND ITS COST
 * -----------------------
 * pal_competencies is keyed on concept_id, but the evidence cannot reach
 * concept level in this database:
 *
 *     questions carrying a concept_id            82 of 62,249
 *     questions carrying a chapter_id        62,246 of 62,249
 *     learner x chapter pairs with answers       24,045
 *     ...of those, on a chapter with concepts         4
 *
 * The concept registry and the chapters learners are actually examined on are
 * disjoint sets, so per-concept mastery is not derivable at all. This command
 * therefore works at CHAPTER grain: one row per learner x subject x chapter,
 * with the chapter id stored in concept_id.
 *
 * That is a deliberate, documented compromise. Everything the screen shows -
 * mastery, Bloom, gaps, velocity, trend - becomes real and per-learner; the
 * only inaccuracy is that "concepts mastered" counts chapters. Re-run at true
 * concept grain once lms_question_master.concept_id is populated.
 *
 * WHAT IS DERIVED, AND FROM WHAT
 * ------------------------------
 *   mastery_score      correct / attempted, over that learner's answers
 *   bloom_level        the Bloom level of the questions they answer correctly,
 *                      from lms_question_mapping (type 82); null where the
 *                      questions carry no Bloom tag, never invented
 *   proficiency_trend  this month's correct-rate against the months before it
 *   last_assessed      their most recent answer in that chapter
 *
 * updated_at is set to the last answer rather than to now(), because
 * LearningVelocityEngine measures velocity by comparing mastery in
 * updated_at windows - stamping today would make every learner look active.
 *
 *   php artisan pal:derive-competencies --dry-run
 *   php artisan pal:derive-competencies --institute=195
 *   php artisan pal:derive-competencies --learner=97801
 */
class DeriveCompetenciesCommand extends Command
{
    protected $signature = 'pal:derive-competencies
        {--institute= : limit to one sub_institute_id}
        {--learner= : limit to a single student id}
        {--min-attempts=3 : ignore learner/chapter pairs with fewer answers than this}
        {--dry-run : report what would be written, write nothing}';

    protected $description = 'PAL: derive pal_competencies (chapter grain) from real exam answers';

    /** lms_mapping_type children of Bloom (82), in ladder order. */
    private const BLOOM_VALUE_LEVELS = [
        88 => 1, // Remember
        87 => 2, // Understand
        86 => 3, // Apply
        85 => 4, // Analyse
        84 => 5, // Evaluate
        83 => 6, // Creating
    ];

    private const BLOOM_MAPPING_TYPE_ID = 82;

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $minAttempts = max(1, (int) $this->option('min-attempts'));

        $this->info('PAL Intelligence - competency derivation (chapter grain)');
        $this->line($this->scopeLine($minAttempts) . '  ·  ' . ($dry ? 'DRY-RUN (no writes)' : 'LIVE WRITE'));
        $this->line(str_repeat('-', 78));

        $this->line('Reading monthly answer aggregates...');
        $monthly = $this->monthlyAggregates();
        $this->line('  ' . number_format($monthly->count()) . ' learner/subject/chapter/month rows');

        $groups = $this->foldToCompetencies($monthly, $minAttempts);
        $this->line('  ' . number_format(count($groups)) . ' learner/chapter competencies derived');

        if ($groups === []) {
            $this->warn('Nothing to write - no learner/chapter pair met the minimum attempts.');

            return self::SUCCESS;
        }

        $this->summarise($groups);

        if ($dry) {
            $this->comment('Dry run - nothing written. Re-run without --dry-run to apply.');

            return self::SUCCESS;
        }

        $written = $this->write($groups);
        $this->info('Done. ' . number_format($written) . ' competency row(s) written.');

        return self::SUCCESS;
    }

    private function scopeLine(int $minAttempts): string
    {
        $parts = ['min ' . $minAttempts . ' answers'];
        if ($this->option('institute')) $parts[] = 'institute ' . $this->option('institute');
        if ($this->option('learner')) $parts[] = 'learner ' . $this->option('learner');

        return implode('  ·  ', $parts);
    }

    /**
     * One pass over the answers, grouped down to learner/subject/chapter/month.
     *
     * Monthly rather than a single total because the trend needs a time series,
     * and this keeps it to one query instead of one per learner. Grouped in SQL
     * so the 2.4M answer rows never reach PHP.
     */
    private function monthlyAggregates()
    {
        $bloomCase = 'CASE m.mapping_value_id ' .
            implode(' ', array_map(
                fn ($valueId, $level) => "WHEN {$valueId} THEN {$level}",
                array_keys(self::BLOOM_VALUE_LEVELS),
                self::BLOOM_VALUE_LEVELS
            )) . ' END';

        return DB::table('lms_online_exam_answer as a')
            ->join('lms_question_master as qm', 'qm.id', '=', 'a.question_id')
            ->leftJoin('lms_question_mapping as m', function ($join) {
                $join->on('m.questionmaster_id', '=', 'a.question_id')
                    ->where('m.mapping_type_id', '=', self::BLOOM_MAPPING_TYPE_ID);
            })
            ->selectRaw("a.student_id, qm.subject_id, qm.chapter_id,
                DATE_FORMAT(a.created_at, '%Y-%m') AS ym,
                COUNT(*) AS attempts,
                SUM(a.ans_status = 'right') AS correct,
                AVG(CASE WHEN a.ans_status = 'right' THEN {$bloomCase} END) AS bloom_right,
                MAX(a.created_at) AS last_at")
            ->whereNotNull('a.student_id')
            ->where('qm.chapter_id', '>', 0)
            ->when($this->option('learner'), fn ($q, $id) => $q->where('a.student_id', $id))
            ->when($this->option('institute'), fn ($q, $id) => $q->where('qm.sub_institute_id', $id))
            ->groupBy('a.student_id', 'qm.subject_id', 'qm.chapter_id', 'ym')
            ->get();
    }

    /** Fold the monthly series into one competency row per learner/chapter. */
    private function foldToCompetencies($monthly, int $minAttempts): array
    {
        $groups = [];

        foreach ($monthly as $row) {
            $key = $row->student_id . '|' . (int) $row->subject_id . '|' . $row->chapter_id;

            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'learner_id' => (int) $row->student_id,
                    'subject_id' => (int) $row->subject_id,
                    'concept_id' => (int) $row->chapter_id,
                    'attempts' => 0,
                    'correct' => 0,
                    'bloom_sum' => 0.0,
                    'bloom_weight' => 0,
                    'last_at' => null,
                    'months' => [],
                ];
            }

            $group = &$groups[$key];
            $group['attempts'] += (int) $row->attempts;
            $group['correct'] += (int) $row->correct;

            if ($row->bloom_right !== null) {
                // Weighted by how many answers that month contributed, so a
                // month with one answer cannot outweigh a month with fifty.
                $group['bloom_sum'] += (float) $row->bloom_right * (int) $row->correct;
                $group['bloom_weight'] += (int) $row->correct;
            }

            if ($group['last_at'] === null || $row->last_at > $group['last_at']) {
                $group['last_at'] = $row->last_at;
            }

            $group['months'][$row->ym] = [
                'attempts' => (int) $row->attempts,
                'correct' => (int) $row->correct,
            ];
            unset($group);
        }

        return array_values(array_filter($groups, fn ($group) => $group['attempts'] >= $minAttempts));
    }

    /**
     * Trend from the month series: the most recent month against everything
     * before it. Fewer than two months of evidence is not a trend, it is a
     * single reading, and is reported as stable rather than guessed at.
     */
    private function trendFor(array $months): string
    {
        if (count($months) < 2) {
            return 'stable';
        }

        ksort($months);
        $keys = array_keys($months);
        $latestKey = array_pop($keys);

        $latest = $months[$latestKey];
        $latestRate = $latest['attempts'] > 0 ? ($latest['correct'] / $latest['attempts']) * 100 : 0;

        $priorAttempts = 0;
        $priorCorrect = 0;
        foreach ($keys as $key) {
            $priorAttempts += $months[$key]['attempts'];
            $priorCorrect += $months[$key]['correct'];
        }

        if ($priorAttempts === 0) {
            return 'stable';
        }

        $priorRate = ($priorCorrect / $priorAttempts) * 100;

        if ($latestRate > $priorRate + 5) return 'improving';
        if ($latestRate < $priorRate - 5) return 'declining';

        return 'stable';
    }

    private function summarise(array $groups): void
    {
        $mastery = array_map(fn ($g) => ($g['correct'] / $g['attempts']) * 100, $groups);
        $withBloom = count(array_filter($groups, fn ($g) => $g['bloom_weight'] > 0));
        $trends = array_count_values(array_map(fn ($g) => $this->trendFor($g['months']), $groups));

        $this->newLine();
        $this->table(['measure', 'value'], [
            ['learners', number_format(count(array_unique(array_map(fn ($g) => $g['learner_id'], $groups))))],
            ['competency rows', number_format(count($groups))],
            ['mean mastery', round(array_sum($mastery) / max(1, count($mastery)), 1) . '%'],
            ['below 50% (knowledge gaps)', number_format(count(array_filter($mastery, fn ($m) => $m < 50)))],
            ['at or above 80% (mastered)', number_format(count(array_filter($mastery, fn ($m) => $m >= 80)))],
            ['rows with a Bloom level', number_format($withBloom) . ' of ' . number_format(count($groups))],
            ['trend improving/stable/declining', ($trends['improving'] ?? 0) . ' / ' . ($trends['stable'] ?? 0) . ' / ' . ($trends['declining'] ?? 0)],
        ]);
    }

    /**
     * Replace this scope's rows rather than appending: a competency is the
     * current view of a learner on a chapter, so re-running must refresh it,
     * not stack a second opinion beside the first.
     */
    private function write(array $groups): int
    {
        $written = 0;

        foreach (array_chunk($groups, 500) as $chunk) {
            $payload = [];

            foreach ($chunk as $group) {
                $bloom = $group['bloom_weight'] > 0
                    ? (int) round($group['bloom_sum'] / $group['bloom_weight'])
                    : null;

                $payload[] = [
                    'learner_id' => $group['learner_id'],
                    'subject_id' => $group['subject_id'],
                    'concept_id' => $group['concept_id'],
                    'mastery_score' => round(($group['correct'] / $group['attempts']) * 100, 2),
                    'bloom_level' => $bloom === null ? null : max(1, min(6, $bloom)),
                    'proficiency_trend' => $this->trendFor($group['months']),
                    'last_assessed' => $group['last_at'],
                    'created_at' => $group['last_at'],
                    // Velocity is measured in updated_at windows; see the class note.
                    'updated_at' => $group['last_at'],
                ];
            }

            DB::transaction(function () use ($payload, &$written) {
                foreach ($payload as $row) {
                    DB::table('pal_competencies')
                        ->where('learner_id', $row['learner_id'])
                        ->where('subject_id', $row['subject_id'])
                        ->where('concept_id', $row['concept_id'])
                        ->delete();
                }
                DB::table('pal_competencies')->insert($payload);
                $written += count($payload);
            });
        }

        return $written;
    }
}
