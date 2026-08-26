<?php

namespace App\Console\Commands\PAL;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Feeds the PAL Intelligence engines with the learning that has already happened.
 *
 * The engines under App\Services\PAL\Intelligence are not hard-coded - they read
 * real tables. The reason the PAL Intelligence screen shows the same figures for
 * every learner is that those tables are empty, so every metric falls through to
 * its `?? 0` / `?? 50` default:
 *
 *     pal_assessment_results        53 rows
 *     pal_learning_sessions          1 row
 *     pal_competencies               4 rows
 *
 * Meanwhile the evidence itself exists, in the legacy LMS tables nothing ever
 * projected into PAL:
 *
 *     lms_online_exam_answer   2,418,087 answered questions (right / wrong)
 *     lms_online_exam            147,890 exam attempts
 *
 * This command projects one into the other. It writes only what the source can
 * genuinely support, and never invents a value:
 *
 *   lms_online_exam_answer -> pal_assessment_results
 *       student_id, question_id, ans_status='right', created_at.
 *       response_time_ms is left NULL: the source does not record it, and a
 *       fabricated duration would silently corrupt every timing-based metric.
 *
 *   lms_online_exam -> pal_learning_sessions
 *       one attempt is one session. duration comes from start_time -> created_at,
 *       engagement from the stored accuracy_rate, mastery from obtain_marks over
 *       the paper's total_marks.
 *
 * NOT written, deliberately: pal_competencies and pal_concept_mastery. Both are
 * keyed on concept_id, and only 82 of 62,249 rows in lms_question_master carry
 * one - so concept-level mastery cannot be derived from this data at all. That
 * needs the question-to-concept mapping populated first; see the note printed at
 * the end of a run.
 *
 * Idempotent without a schema change: a row is skipped when the same
 * (learner, question, timestamp) is already present, so re-running only tops up.
 *
 *   php artisan pal:sync-learner-evidence --dry-run
 *   php artisan pal:sync-learner-evidence --institute=195 --since=2026-01-01
 *   php artisan pal:sync-learner-evidence --learner=97801
 */
class SyncLearnerEvidenceCommand extends Command
{
    protected $signature = 'pal:sync-learner-evidence
        {--institute= : limit to one sub_institute_id (via the paper the answer belongs to)}
        {--learner= : limit to a single student id}
        {--since= : only source rows created on or after this date (YYYY-MM-DD)}
        {--chunk=2000 : rows per batch}
        {--limit= : stop after this many source rows, for a scoped trial run}
        {--skip-sessions : project answers only, not exam attempts}
        {--dry-run : report what would be written, write nothing}';

    protected $description = 'PAL: project real LMS exam activity into the tables the Intelligence engines read';

    /**
     * Above this many existing rows the whole identity set no longer belongs in
     * memory, and duplicate checks fall back to one lookup per batch.
     */
    private const PRELOAD_GUARD = 250000;

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $chunk = max(100, (int) $this->option('chunk'));

        $this->info('PAL Intelligence - learner evidence sync');
        $this->line($this->scopeLine() . '  ·  ' . ($dry ? 'DRY-RUN (no writes)' : 'LIVE WRITE'));
        $this->line(str_repeat('-', 78));

        $answers = $this->syncAnswers($dry, $chunk);

        $sessions = ['candidates' => 0, 'written' => 0, 'skipped' => 0];
        if (! $this->option('skip-sessions')) {
            $sessions = $this->syncSessions($dry, $chunk);
        }

        $this->line(str_repeat('-', 78));
        $this->table(
            ['target', 'source rows', ($dry ? 'would write' : 'written'), 'already present'],
            [
                ['pal_assessment_results', number_format($answers['candidates']), number_format($answers['written']), number_format($answers['skipped'])],
                ['pal_learning_sessions', number_format($sessions['candidates']), number_format($sessions['written']), number_format($sessions['skipped'])],
            ]
        );

        $this->reportConceptGap();

        if ($dry) {
            $this->comment('Dry run - nothing written. Re-run without --dry-run to apply.');
        }

        return self::SUCCESS;
    }

    private function scopeLine(): string
    {
        $parts = [];
        if ($this->option('institute')) $parts[] = 'institute ' . $this->option('institute');
        if ($this->option('learner')) $parts[] = 'learner ' . $this->option('learner');
        if ($this->option('since')) $parts[] = 'since ' . $this->option('since');
        if ($this->option('limit')) $parts[] = 'first ' . $this->option('limit') . ' rows';

        return $parts === [] ? 'all learners, all history' : implode('  ·  ', $parts);
    }

    /** lms_online_exam_answer -> pal_assessment_results */
    private function syncAnswers(bool $dry, int $chunk): array
    {
        $this->line('Answers -> pal_assessment_results');

        $query = DB::table('lms_online_exam_answer as a')
            ->selectRaw('a.id, a.student_id, a.question_id, a.ans_status, a.created_at')
            ->whereNotNull('a.student_id')
            ->whereNotNull('a.question_id')
            ->when($this->option('learner'), fn ($q, $id) => $q->where('a.student_id', $id))
            ->when($this->option('since'), fn ($q, $date) => $q->where('a.created_at', '>=', $date))
            ->when($this->option('institute'), function ($q, $institute) {
                // The answer row carries no tenant of its own; the paper does.
                $q->join('question_paper as qp', 'qp.id', '=', 'a.question_paper_id')
                    ->where('qp.sub_institute_id', $institute);
            })
            ->orderBy('a.id');

        return $this->project($query, $chunk, $dry, function (array $rows) {
            $payload = [];
            foreach ($rows as $row) {
                $isCorrect = strtolower(trim((string) $row->ans_status)) === 'right';
                $payload[] = [
                    'learner_id' => (int) $row->student_id,
                    'question_id' => (int) $row->question_id,
                    'is_correct' => $isCorrect ? 1 : 0,
                    // The source has no per-question timing. Left NULL rather than
                    // guessed, so response-time metrics stay honestly empty.
                    'response_time_ms' => null,
                    'score' => $isCorrect ? 1 : 0,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->created_at,
                ];
            }

            return $payload;
        }, 'pal_assessment_results', ['learner_id', 'question_id', 'created_at']);
    }

    /** lms_online_exam -> pal_learning_sessions */
    private function syncSessions(bool $dry, int $chunk): array
    {
        $this->line('Exam attempts -> pal_learning_sessions');

        $query = DB::table('lms_online_exam as oe')
            ->join('question_paper as qp', 'qp.id', '=', 'oe.question_paper_id')
            ->selectRaw('oe.id, oe.student_id, qp.subject_id, oe.total_right, oe.total_wrong,
                oe.obtain_marks, qp.total_marks, oe.accuracy_rate, oe.start_time, oe.created_at')
            ->whereNotNull('oe.student_id')
            ->when($this->option('learner'), fn ($q, $id) => $q->where('oe.student_id', $id))
            ->when($this->option('since'), fn ($q, $date) => $q->where('oe.created_at', '>=', $date))
            ->when($this->option('institute'), fn ($q, $institute) => $q->where('qp.sub_institute_id', $institute))
            ->orderBy('oe.id');

        return $this->project($query, $chunk, $dry, function (array $rows) {
            $payload = [];
            foreach ($rows as $row) {
                $minutes = 0;
                if ($row->start_time && $row->created_at) {
                    $minutes = max(0, (int) round(
                        (strtotime((string) $row->created_at) - strtotime((string) $row->start_time)) / 60
                    ));
                }

                $totalMarks = (float) $row->total_marks;

                $payload[] = [
                    'learner_id' => (int) $row->student_id,
                    'subject_id' => $row->subject_id ? (int) $row->subject_id : null,
                    'status' => 'completed',
                    'difficulty_level' => null,
                    'duration_minutes' => $minutes,
                    'interaction_count' => (int) $row->total_right + (int) $row->total_wrong,
                    'engagement_score' => $row->accuracy_rate === null ? null : (float) $row->accuracy_rate,
                    'mastery_score' => $totalMarks > 0
                        ? round(((float) $row->obtain_marks / $totalMarks) * 100, 2)
                        : null,
                    // The legacy attempt records no device; contextual inference
                    // already treats an unknown device as unknown.
                    'device_type' => null,
                    'initiated_by' => 'lms_online_exam',
                    'created_at' => $row->created_at,
                    'updated_at' => $row->created_at,
                ];
            }

            return $payload;
        }, 'pal_learning_sessions', ['learner_id', 'created_at']);
    }

    /**
     * Walk the source in batches, map each batch, drop rows already projected,
     * and insert the rest.
     *
     * @param  callable(array): array  $map
     * @param  string[]  $identity  columns that make a projected row unique
     */
    private function project($query, int $chunk, bool $dry, callable $map, string $target, array $identity): array
    {
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        $stats = ['candidates' => 0, 'written' => 0, 'skipped' => 0];
        $existing = $this->loadExistingKeys($target, $identity);
        $reportEvery = 50000;
        $nextReport = $reportEvery;

        $query->chunk($chunk, function ($rows) use (
            &$stats, $map, $target, $identity, $dry, $limit, $existing, &$nextReport, $reportEvery
        ) {
            $rows = $rows->all();
            if ($limit !== null && $stats['candidates'] + count($rows) > $limit) {
                $rows = array_slice($rows, 0, max(0, $limit - $stats['candidates']));
            }
            if ($rows === []) {
                return false;
            }

            $stats['candidates'] += count($rows);
            $payload = $map($rows);
            $fresh = $this->withoutExisting($payload, $target, $identity, $stats, $existing);

            if ($fresh !== [] && ! $dry) {
                DB::table($target)->insert($fresh);
            }
            $stats['written'] += count($fresh);

            // A multi-million row walk with no output reads as a hang.
            if ($stats['candidates'] >= $nextReport) {
                $this->line('    ' . number_format($stats['candidates']) . ' source rows read, '
                    . number_format($stats['written']) . ' written');
                $nextReport += $reportEvery;
            }

            if ($limit !== null && $stats['candidates'] >= $limit) {
                return false;
            }

            return true;
        });

        return $stats;
    }

    /**
     * The identity keys already present in a target, loaded once.
     *
     * Deduping per batch instead would mean a lookup against a table that grows
     * to millions of rows, twelve hundred times over. These tables start
     * effectively empty, so the whole existing set fits in memory and the
     * comparison costs nothing. Past the guard it would not, and a per-batch
     * lookup is the honest fallback.
     */
    private function loadExistingKeys(string $target, array $identity): ?Collection
    {
        $count = (int) DB::table($target)->count();

        if ($count > self::PRELOAD_GUARD) {
            $this->warn("  {$target} already holds " . number_format($count) . ' rows - '
                . 'falling back to per-batch duplicate checks, which is slower.');

            return null;
        }

        $this->line('  ' . number_format($count) . " row(s) already in {$target}");

        return DB::table($target)
            ->select($identity)
            ->get()
            ->map(fn ($row) => $this->identityKey((array) $row, $identity))
            ->flip();
    }

    /**
     * Drop rows already projected.
     *
     * There is no source-id column on the PAL tables to key off, so identity is
     * the natural one: the same learner answering the same question at the same
     * instant is the same event.
     */
    private function withoutExisting(
        array $payload,
        string $target,
        array $identity,
        array &$stats,
        ?Collection $existing
    ): array {
        if ($payload === []) {
            return [];
        }

        $seen = $existing ?? DB::table($target)
            ->select($identity)
            ->where(function ($query) use ($payload, $identity) {
                foreach ($payload as $row) {
                    $query->orWhere(function ($inner) use ($row, $identity) {
                        foreach ($identity as $column) {
                            $inner->where($column, $row[$column]);
                        }
                    });
                }
            })
            ->get()
            ->map(fn ($row) => $this->identityKey((array) $row, $identity))
            ->flip();

        $fresh = [];
        foreach ($payload as $row) {
            $key = $this->identityKey($row, $identity);
            if ($seen->has($key)) {
                $stats['skipped']++;
                continue;
            }
            // Remember it, so duplicates within this run are caught too.
            $seen->put($key, true);
            $fresh[] = $row;
        }

        return $fresh;
    }

    private function identityKey(array $row, array $identity): string
    {
        return implode('|', array_map(fn ($column) => (string) ($row[$column] ?? ''), $identity));
    }

    /**
     * The part of PAL Intelligence this command cannot fix, stated plainly rather
     * than left for someone to discover in the UI.
     */
    private function reportConceptGap(): void
    {
        $total = (int) DB::table('lms_question_master')->count();
        $linked = (int) DB::table('lms_question_master')
            ->whereNotNull('concept_id')->where('concept_id', '<>', 0)->count();

        $this->newLine();
        $this->warn('Concept-level intelligence is still blocked.');
        $this->line("  Only {$linked} of {$total} questions in lms_question_master carry a concept_id,");
        $this->line('  so pal_competencies and pal_concept_mastery cannot be derived from attempts.');
        $this->line('  Mastery maps, concept gaps and misconception clustering stay empty until');
        $this->line('  question-to-concept mapping is populated.');
    }
}
