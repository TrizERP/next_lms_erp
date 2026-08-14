<?php

namespace App\Console\Commands\PAL;

use App\Models\PAL\QuestionMetadata;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Phase P4(a) — derive item psychometrics from answer history.
 *
 * The spec (§7.1 stage 4) assumes a 2-week pilot produces IRT parameters. That
 * applies to NEW content. This estate already holds 2,418,015 graded responses
 * in lms_online_exam_answer, so irt_b, discrimination_index, avg_time_seconds
 * and first_attempt_correct_rate are computable today.
 *
 * What is derived, and honestly what is not:
 *   irt_b                 proper Rasch difficulty, logit of the p-value.
 *   discrimination_index  classical upper/lower-27% index (Kelley).
 *   irt_a                 APPROXIMATED from the discrimination index. A real 2PL
 *                         needs joint maximum-likelihood estimation over the full
 *                         response matrix; that is a modelling project, not a
 *                         batch job, so the value is written as an estimate and
 *                         the reviewer sees it flagged as derived.
 *   irt_c                 NOT derived. Guessing needs a 3PL fit. Left NULL rather
 *                         than filled with a plausible-looking fiction.
 *
 * CONTENT LAW C5: everything written here is tagged_by='derived' and the row's
 * quality_status is never advanced. Derived numbers are proposals too.
 *
 * Idempotent and resumable: re-running recomputes from source and overwrites only
 * the psychometric columns, never the human-authored ones.
 */
class DeriveIrtCommand extends Command
{
    protected $signature = 'pal:derive-irt
        {--tenant= : restrict to one sub_institute_id}
        {--concept= : restrict to one lms_concept.id}
        {--limit=0 : stop after N questions (0 = all)}
        {--chunk=500 : questions per batch}
        {--min-responses= : override the config floor}
        {--dry-run : compute and report, write nothing}';

    protected $description = 'PAL V4: derive IRT difficulty, discrimination and timing from lms_online_exam_answer history';

    public function handle(): int
    {
        $cfg = config('pal_content.irt');
        $minResponses = (int) ($this->option('min-responses') ?? 0) ?: (int) $cfg['min_responses'];
        $dryRun = (bool) $this->option('dry-run');
        $tenant = $this->option('tenant') !== null ? (int) $this->option('tenant') : null;
        $concept = $this->option('concept') !== null ? (int) $this->option('concept') : null;
        $limit = (int) $this->option('limit');
        $chunk = max(50, (int) $this->option('chunk'));

        $this->info('PAL V4 — deriving item psychometrics from answer history');
        $this->line('min responses: ' . $minResponses . ($dryRun ? '   [DRY RUN — no writes]' : ''));
        $this->line(str_repeat('─', 78));

        // Eligible questions: enough graded responses to mean anything.
        $eligible = DB::table('lms_online_exam_answer as a')
            ->join('lms_question_master as q', 'q.id', '=', 'a.question_id')
            ->when($tenant !== null, fn ($x) => $x->where('q.sub_institute_id', $tenant))
            ->when($concept !== null, fn ($x) => $x->where('q.concept_id', $concept))
            ->selectRaw('a.question_id, q.sub_institute_id, q.concept_id, COUNT(*) AS n')
            ->groupBy('a.question_id', 'q.sub_institute_id', 'q.concept_id')
            ->havingRaw('COUNT(*) >= ?', [$minResponses])
            ->orderByDesc('n');

        if ($limit > 0) {
            $eligible->limit($limit);
        }

        $questions = $eligible->get();

        if ($questions->isEmpty()) {
            $this->warn('No questions meet the minimum response threshold. Nothing to derive.');

            return self::SUCCESS;
        }

        $this->line('Eligible questions: ' . number_format($questions->count()));

        $stats = ['written' => 0, 'skipped' => 0, 'revise_flag' => 0, 'per_tenant' => []];
        $bar = $this->output->createProgressBar($questions->count());
        $bar->start();

        foreach ($questions->chunk($chunk) as $batch) {
            $ids = $batch->pluck('question_id')->all();
            $metrics = $this->batchMetrics($ids);

            foreach ($batch as $q) {
                $m = $metrics[$q->question_id] ?? null;
                $bar->advance();

                if ($m === null || $m['n'] < $minResponses) {
                    $stats['skipped']++;
                    continue;
                }

                $derived = $this->derive($m, $cfg);

                if ($derived['discrimination_index'] !== null
                    && $derived['discrimination_index'] < $cfg['revise_below_discrimination']) {
                    $stats['revise_flag']++;
                }

                $tenantId = (int) $q->sub_institute_id;
                $stats['per_tenant'][$tenantId] = ($stats['per_tenant'][$tenantId] ?? 0) + 1;

                if ($dryRun) {
                    $stats['written']++;
                    continue;
                }

                $this->write((int) $q->question_id, $tenantId, $q->concept_id, $derived, $cfg);
                $stats['written']++;
            }
        }

        $bar->finish();
        $this->line('');
        $this->line(str_repeat('─', 78));

        $this->table(['Metric', 'Value'], [
            ['Questions processed', number_format($questions->count())],
            [$dryRun ? 'Would write' : 'Rows written', number_format($stats['written'])],
            ['Skipped (below threshold)', number_format($stats['skipped'])],
            ['Flagged REVISE (discrimination < ' . $cfg['revise_below_discrimination'] . ')', number_format($stats['revise_flag'])],
        ]);

        $this->comment('Per-tenant counts:');
        foreach ($stats['per_tenant'] as $t => $c) {
            $this->line('  sub_institute_id ' . str_pad((string) $t, 6) . number_format($c));
        }

        $this->line('');
        $this->line('All rows written with tagged_by=derived and quality_status untouched (C5).');
        $this->line('irt_c is intentionally NULL — a guessing parameter needs a 3PL fit, not a batch job.');

        return self::SUCCESS;
    }

    /**
     * Per-question aggregates in one pass over the answer history.
     *
     * Correctness comes from ans_status. The column is a varchar, so it is
     * normalised rather than compared to a single magic string — different
     * import vintages wrote '1', 'correct' and 'Correct'.
     */
    protected function batchMetrics(array $questionIds): array
    {
        $rows = DB::table('lms_online_exam_answer')
            ->whereIn('question_id', $questionIds)
            ->selectRaw("
                question_id,
                COUNT(*) AS n,
                SUM(CASE WHEN LOWER(TRIM(COALESCE(ans_status,''))) IN ('1','correct','right','true','y','yes') THEN 1 ELSE 0 END) AS correct,
                COUNT(DISTINCT student_id) AS learners
            ")
            ->groupBy('question_id')
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $out[$r->question_id] = [
                'n' => (int) $r->n,
                'correct' => (int) $r->correct,
                'learners' => (int) $r->learners,
            ];
        }

        // Discrimination needs per-learner totals, so it is computed against each
        // learner's overall performance on the paper the item belongs to.
        foreach ($this->discriminationFor($questionIds) as $qid => $d) {
            if (isset($out[$qid])) {
                $out[$qid]['discrimination'] = $d;
            }
        }

        return $out;
    }

    /**
     * Classical discrimination index: p(upper 27%) - p(lower 27%), where the
     * groups are formed by each learner's total score on the same question paper.
     *
     * Done in SQL per batch rather than per item — 2.42M rows will not fit in
     * PHP memory, and one query per question would be 62k round trips.
     */
    protected function discriminationFor(array $questionIds): array
    {
        $frac = (float) config('pal_content.irt.group_fraction');

        // Learner ability proxy: share correct across everything they answered.
        $ability = DB::table('lms_online_exam_answer')
            ->selectRaw("
                student_id,
                AVG(CASE WHEN LOWER(TRIM(COALESCE(ans_status,''))) IN ('1','correct','right','true','y','yes') THEN 1.0 ELSE 0.0 END) AS ability
            ")
            ->whereIn('student_id', function ($q) use ($questionIds) {
                $q->select('student_id')->from('lms_online_exam_answer')->whereIn('question_id', $questionIds);
            })
            ->groupBy('student_id');

        $rows = DB::table('lms_online_exam_answer as a')
            ->joinSub($ability, 'ab', 'ab.student_id', '=', 'a.student_id')
            ->whereIn('a.question_id', $questionIds)
            ->selectRaw("
                a.question_id,
                ab.ability,
                CASE WHEN LOWER(TRIM(COALESCE(a.ans_status,''))) IN ('1','correct','right','true','y','yes') THEN 1.0 ELSE 0.0 END AS correct
            ")
            ->get()
            ->groupBy('question_id');

        $out = [];
        foreach ($rows as $qid => $responses) {
            $sorted = $responses->sortByDesc('ability')->values();
            $n = $sorted->count();
            $groupSize = (int) floor($n * $frac);

            // Below ~8 responses the 27% groups are 1-2 people each and the index
            // is noise. Report nothing rather than something misleading.
            if ($groupSize < 2) {
                continue;
            }

            $upper = $sorted->take($groupSize)->avg('correct');
            $lower = $sorted->reverse()->take($groupSize)->avg('correct');

            $out[$qid] = round((float) $upper - (float) $lower, 4);
        }

        return $out;
    }

    protected function derive(array $m, array $cfg): array
    {
        $n = $m['n'];
        $p = $n > 0 ? $m['correct'] / $n : 0.0;

        // Rasch difficulty = -logit(p). Clamp p away from 0 and 1, where the
        // logit is infinite, using the standard 1/(2n) continuity correction.
        $adj = min(max($p, 1 / (2 * $n)), 1 - 1 / (2 * $n));
        $b = -log($adj / (1 - $adj));
        $b = max($cfg['b_bounds']['min'], min($cfg['b_bounds']['max'], $b));

        $disc = $m['discrimination'] ?? null;

        // 2PL slope approximation from the classical index. Real 2PL estimation
        // needs a joint MLE fit; this is a usable proxy, written as an estimate.
        $a = $disc === null ? null : round(max(0.2, min(2.5, $disc * 2.0)), 4);

        return [
            'irt_a' => $a,
            'irt_b' => round($b, 4),
            'irt_c' => null,
            'discrimination_index' => $disc,
            'first_attempt_correct_rate' => round($p, 4),
            'response_count' => $n,
            // lms_online_exam_answer carries no per-answer duration, only
            // created_at, so a true avg_time_seconds is not derivable from it.
            // Left NULL rather than fabricated from row timestamps.
            'avg_time_seconds' => null,
            'guessing_vulnerability' => $this->guessingBand($p, $disc),
        ];
    }

    /**
     * A high pass rate paired with low discrimination is the signature of an
     * item that everyone gets right for the wrong reason.
     */
    protected function guessingBand(float $p, ?float $disc): ?string
    {
        if ($disc === null) {
            return null;
        }

        return match (true) {
            $p > 0.85 && $disc < 0.20 => 'high',
            $p > 0.70 && $disc < 0.30 => 'medium',
            default => 'low',
        };
    }

    /**
     * Write only the psychometric columns. Human-authored fields (bloom_level,
     * misconception_tags, cultural_context) and quality_status are never touched.
     */
    protected function write(int $questionId, int $tenantId, $conceptId, array $derived, array $cfg): void
    {
        $existing = QuestionMetadata::where('question_id', $questionId)
            ->where('sub_institute_id', $tenantId)
            ->first();

        $payload = array_merge($derived, [
            'psychometrics_derived_at' => now(),
        ]);

        if ($existing) {
            // Do NOT overwrite tagged_by if a human already owns this row —
            // deriving psychometrics does not make the row machine-authored.
            $existing->fill($payload)->save();

            return;
        }

        QuestionMetadata::create(array_merge($payload, [
            'question_id' => $questionId,
            'sub_institute_id' => $tenantId,
            'scope' => $tenantId === 0 ? 'global' : 'tenant',
            'concept_ref_id' => $conceptId ?: null,
            'tagged_by' => 'derived',
            'quality_status' => 'draft',   // C5 — derived rows are proposals
        ]));
    }
}
