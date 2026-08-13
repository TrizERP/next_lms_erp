<?php

namespace App\Console\Commands\PAL;

use App\Services\PAL\Content\MisconceptionLibraryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Phase P0 — measure before building.
 *
 * Read-only. Reports how much of the live content estate carries Content
 * Intelligence metadata, per tenant, plus the C6 audit and the IRT-eligible
 * question count. This is the baseline every later phase is measured against.
 */
class ContentCoverageCommand extends Command
{
    protected $signature = 'pal:content-coverage
        {--tenant= : restrict to one sub_institute_id}
        {--top=15 : how many tenants to list}
        {--concepts : also show per-concept ladder gaps}
        {--json : emit machine-readable JSON instead of tables}';

    protected $description = 'PAL V4: report Content Intelligence metadata coverage over the live LMS content estate (read-only)';

    public function handle(MisconceptionLibraryService $misconceptions): int
    {
        $tenant = $this->option('tenant') !== null ? (int) $this->option('tenant') : null;

        $report = [
            'generated_at' => now()->toIso8601String(),
            'tenant_filter' => $tenant,
            'totals' => $this->totals($tenant),
            'per_tenant' => $this->perTenant($tenant, (int) $this->option('top')),
            'bloom_distribution' => $this->bloomDistribution($tenant),
            'quality_pipeline' => $this->qualityPipeline($tenant),
            'misconceptions' => $misconceptions->libraryHealth($tenant),
            'irt' => $this->irtEligibility($tenant),
            'existing_lms_tags' => $this->existingLmsTags(),
            'linkage' => $this->linkage($tenant),
        ];

        if ($this->option('concepts')) {
            $report['concept_gaps'] = $this->conceptGaps($tenant);
        }

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->render($report);

        return self::SUCCESS;
    }

    // ── Sections ─────────────────────────────────────────────────────────────

    protected function totals(?int $tenant): array
    {
        $q = fn (string $table) => $tenant === null
            ? DB::table($table)
            : DB::table($table)->where('sub_institute_id', $tenant);

        $questions = $q('lms_question_master')->count();
        $content = $q('content_master')->count();
        $concepts = $q('lms_concept')->count();

        $qTagged = $q('pal_question_metadata')->count();
        $cTagged = $q('pal_content_metadata')->count();
        $cpTagged = $q('pal_concept_metadata')->count();

        $qApproved = $q('pal_question_metadata')->where('quality_status', 'approved')->count();
        $cApproved = $q('pal_content_metadata')->where('quality_status', 'approved')->count();

        return [
            'questions' => [
                'total' => $questions,
                'tagged' => $qTagged,
                'approved' => $qApproved,
                'coverage' => $this->pct($qTagged, $questions),
                'approved_coverage' => $this->pct($qApproved, $questions),
            ],
            'content' => [
                'total' => $content,
                'tagged' => $cTagged,
                'approved' => $cApproved,
                'coverage' => $this->pct($cTagged, $content),
                'approved_coverage' => $this->pct($cApproved, $content),
            ],
            'concepts' => [
                'total' => $concepts,
                'tagged' => $cpTagged,
                'coverage' => $this->pct($cpTagged, $concepts),
            ],
        ];
    }

    protected function perTenant(?int $tenant, int $top): array
    {
        $questions = DB::table('lms_question_master')
            ->selectRaw('sub_institute_id, COUNT(*) AS c')
            ->when($tenant !== null, fn ($q) => $q->where('sub_institute_id', $tenant))
            ->groupBy('sub_institute_id')
            ->pluck('c', 'sub_institute_id');

        $content = DB::table('content_master')
            ->selectRaw('sub_institute_id, COUNT(*) AS c')
            ->when($tenant !== null, fn ($q) => $q->where('sub_institute_id', $tenant))
            ->groupBy('sub_institute_id')
            ->pluck('c', 'sub_institute_id');

        $qTagged = DB::table('pal_question_metadata')
            ->selectRaw('sub_institute_id, COUNT(*) AS c')
            ->groupBy('sub_institute_id')->pluck('c', 'sub_institute_id');

        $cTagged = DB::table('pal_content_metadata')
            ->selectRaw('sub_institute_id, COUNT(*) AS c')
            ->groupBy('sub_institute_id')->pluck('c', 'sub_institute_id');

        $ids = collect($questions->keys())->merge($content->keys())->unique()->values();

        $rows = $ids->map(fn ($id) => [
            'sub_institute_id' => (int) $id,
            'questions' => (int) ($questions[$id] ?? 0),
            'questions_tagged' => (int) ($qTagged[$id] ?? 0),
            'content' => (int) ($content[$id] ?? 0),
            'content_tagged' => (int) ($cTagged[$id] ?? 0),
        ])->sortByDesc(fn ($r) => $r['questions'] + $r['content'])->values();

        return $rows->take($top)->all();
    }

    protected function bloomDistribution(?int $tenant): array
    {
        $rows = DB::table('pal_question_metadata')
            ->when($tenant !== null, fn ($q) => $q->where('sub_institute_id', $tenant))
            ->selectRaw('bloom_level, practice_level, COUNT(*) AS c')
            ->groupBy('bloom_level', 'practice_level')
            ->get();

        $out = [];
        foreach (config('pal_content.bloom_levels') as $key => $def) {
            $out[$key] = (int) $rows->where('bloom_level', $key)->sum('c');
        }
        $out['untagged'] = (int) $rows->whereNull('bloom_level')->sum('c');

        return $out;
    }

    protected function qualityPipeline(?int $tenant): array
    {
        $out = [];
        foreach (['pal_question_metadata' => 'questions', 'pal_content_metadata' => 'content'] as $table => $label) {
            $rows = DB::table($table)
                ->when($tenant !== null, fn ($q) => $q->where('sub_institute_id', $tenant))
                ->selectRaw('quality_status, tagged_by, COUNT(*) AS c')
                ->groupBy('quality_status', 'tagged_by')
                ->get();

            foreach (array_keys(config('pal_content.quality_statuses')) as $status) {
                $out[$label][$status] = (int) $rows->where('quality_status', $status)->sum('c');
            }

            // CONTENT LAW C5 audit: an 'approved' row written by a machine is a
            // defect, not a statistic.
            $out[$label]['C5_machine_approved'] = (int) $rows
                ->where('quality_status', 'approved')
                ->whereIn('tagged_by', ['ai', 'derived'])
                ->sum('c');
        }

        return $out;
    }

    /**
     * How many questions have enough graded responses for IRT to mean anything.
     * lms_online_exam_answer holds 2.42M rows, so this is derivable today —
     * the spec's 2-week pilot applies only to brand-new content.
     */
    protected function irtEligibility(?int $tenant): array
    {
        $min = (int) config('pal_content.irt.min_responses');

        $sub = DB::table('lms_online_exam_answer as a')
            ->selectRaw('a.question_id, COUNT(*) AS n')
            ->groupBy('a.question_id');

        $eligible = DB::query()->fromSub($sub, 't')
            ->where('t.n', '>=', $min)
            ->count();

        $totalAnswered = DB::query()->fromSub($sub, 't')->count();

        $derived = DB::table('pal_question_metadata')
            ->when($tenant !== null, fn ($q) => $q->where('sub_institute_id', $tenant))
            ->whereNotNull('discrimination_index')
            ->count();

        return [
            'min_responses' => $min,
            'questions_with_any_response' => $totalAnswered,
            'questions_irt_eligible' => $eligible,
            'questions_already_derived' => $derived,
            'remaining' => max(0, $eligible - $derived),
        ];
    }

    /**
     * What the LMS already tags through content_mapping_type. Bloom ids 83-88 and
     * the pedagogy rows exist today; the migration reuses them rather than
     * forking the taxonomy, so this shows how much is inheritable for free.
     */
    protected function existingLmsTags(): array
    {
        $bloomIds = collect(config('pal_content.bloom_levels'))->pluck('mapping_type_id')->all();

        $bloomTagged = DB::table('content_mapping_type')
            ->whereIn('mapping_value_id', $bloomIds)
            ->distinct('content_id')
            ->count('content_id');

        $pedagogyParent = (int) config('pal_content.pedagogy_source.parent_id');
        $pedagogyIds = DB::table('lms_mapping_type')->where('parent_id', $pedagogyParent)->pluck('id')->all();

        $pedagogyTagged = $pedagogyIds === [] ? 0 : DB::table('content_mapping_type')
            ->whereIn('mapping_value_id', $pedagogyIds)
            ->distinct('content_id')
            ->count('content_id');

        return [
            'content_with_bloom_mapping' => $bloomTagged,
            'content_with_pedagogy_mapping' => $pedagogyTagged,
            'pedagogy_vocabulary_size' => count($pedagogyIds),
        ];
    }

    /**
     * Curriculum linkage — the precondition the whole layer routes on.
     *
     * The spec assumes every question and content row resolves to a :Concept.
     * On this estate concept_id is empty almost everywhere, so this section
     * reports the truth rather than letting a 0% coverage number look like a
     * tagging backlog when it is actually a linkage gap.
     */
    protected function linkage(?int $tenant): array
    {
        $q = fn (string $t) => $tenant === null ? DB::table($t) : DB::table($t)->where('sub_institute_id', $tenant);

        $qTotal = $q('lms_question_master')->count();
        $cTotal = $q('content_master')->count();

        return [
            'questions' => [
                'total' => $qTotal,
                'with_concept_id' => $q('lms_question_master')->where('concept_id', '>', 0)->count(),
                'with_chapter_id' => $q('lms_question_master')->where('chapter_id', '>', 0)->count(),
                'with_topic_id' => $q('lms_question_master')->where('topic_id', '>', 0)->count(),
            ],
            'content' => [
                'total' => $cTotal,
                'with_concept_id' => $q('content_master')->where('concept_id', '>', 0)->count(),
                'with_chapter_id' => $q('content_master')->where('chapter_id', '>', 0)->count(),
                'with_topic_id' => $q('content_master')->where('topic_id', '>', 0)->count(),
            ],
            'concepts' => [
                'rows_in_lms_concept' => $q('lms_concept')->count(),
                'chapters_covered_by_lms_concept' => DB::table('lms_concept')
                    ->when($tenant !== null, fn ($x) => $x->where('sub_institute_id', $tenant))
                    ->where('chapter_id', '>', 0)->distinct('chapter_id')->count('chapter_id'),
                'chapters_used_by_questions' => DB::table('lms_question_master')
                    ->when($tenant !== null, fn ($x) => $x->where('sub_institute_id', $tenant))
                    ->where('chapter_id', '>', 0)->distinct('chapter_id')->count('chapter_id'),
            ],
            'chapter_join' => $this->chapterJoin($tenant),
        ];
    }

    /**
     * Is chapter_id actually usable as the routing key?
     *
     * Two different questions, and they have different answers here:
     *
     *   "does it resolve to a chapter NAME?"  — mostly no. chapter_master holds
     *      110 rows while questions reference 1,347 distinct chapter ids. This is
     *      the same discrepancy the Neo4j runbook records as R5.
     *
     *   "does it join questions to content?"  — yes. The overlap is what the
     *      variant router needs: given a learner's question, find content on the
     *      same chapter. A missing display label does not break routing.
     *
     * Reporting only the first number would wrongly suggest the layer cannot run.
     */
    protected function chapterJoin(?int $tenant): array
    {
        $qCh = DB::table('lms_question_master')
            ->when($tenant !== null, fn ($x) => $x->where('sub_institute_id', $tenant))
            ->where('chapter_id', '>', 0)->distinct()->pluck('chapter_id');

        $cCh = DB::table('content_master')
            ->when($tenant !== null, fn ($x) => $x->where('sub_institute_id', $tenant))
            ->where('chapter_id', '>', 0)->distinct()->pluck('chapter_id');

        $known = DB::table('chapter_master')->pluck('id');

        return [
            'chapter_master_rows' => $known->count(),
            'question_chapters' => $qCh->count(),
            'content_chapters' => $cCh->count(),
            'question_chapters_named' => $qCh->intersect($known)->count(),
            // The number that decides whether routing is possible at all.
            'question_content_overlap' => $qCh->intersect($cCh)->count(),
        ];
    }

    /**
     * Chapters with the most learner traffic and the least ladder coverage —
     * the work queue for P3/P4, ordered so the highest-exposure gaps come first.
     *
     * Grouped by chapter, not concept: concept_id is unpopulated on the live
     * estate (see linkage() above), and grouping by it would return an empty
     * work queue while 2.4M answers sit unaddressed.
     */
    protected function conceptGaps(?int $tenant): array
    {
        $rows = DB::table('lms_question_master as q')
            ->join('lms_online_exam_answer as a', 'a.question_id', '=', 'q.id')
            ->when($tenant !== null, fn ($x) => $x->where('q.sub_institute_id', $tenant))
            ->where('q.chapter_id', '>', 0)
            ->selectRaw('q.chapter_id, COUNT(*) AS responses, COUNT(DISTINCT q.id) AS questions')
            ->groupBy('q.chapter_id')
            ->orderByDesc('responses')
            ->limit(20)
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $chapterIds = $rows->pluck('chapter_id')->all();
        $names = DB::table('chapter_master')->whereIn('id', $chapterIds)->pluck('chapter_name', 'id');

        $tagged = DB::table('pal_question_metadata')
            ->whereIn('chapter_ref_id', $chapterIds)
            ->selectRaw('chapter_ref_id, COUNT(*) AS c')
            ->groupBy('chapter_ref_id')->pluck('c', 'chapter_ref_id');

        $misconceptions = DB::table('pal_misconception_library')
            ->whereIn('chapter_ref_id', $chapterIds)
            ->selectRaw('chapter_ref_id, COUNT(*) AS c')
            ->groupBy('chapter_ref_id')->pluck('c', 'chapter_ref_id');

        return $rows->map(fn ($r) => [
            'chapter_id' => (int) $r->chapter_id,
            'name' => $names[$r->chapter_id] ?? '(not in chapter_master)',
            'responses' => (int) $r->responses,
            'questions' => (int) $r->questions,
            'questions_tagged' => (int) ($tagged[$r->chapter_id] ?? 0),
            'misconceptions' => (int) ($misconceptions[$r->chapter_id] ?? 0),
            // Spec §4.1 minimum viable: 3 misconceptions per high-frequency topic.
            'misconception_gap' => max(0, 3 - (int) ($misconceptions[$r->chapter_id] ?? 0)),
        ])->all();
    }

    // ── Rendering ────────────────────────────────────────────────────────────

    protected function render(array $r): void
    {
        $this->info('PAL V4 — Content Intelligence coverage');
        $this->line('Generated ' . $r['generated_at'] . ($r['tenant_filter'] !== null ? '  ·  tenant ' . $r['tenant_filter'] : '  ·  all tenants'));
        $this->line(str_repeat('─', 78));

        $this->line('');
        $this->comment('ESTATE COVERAGE');
        $this->table(
            ['Estate', 'Total', 'Tagged', 'Coverage', 'Approved', 'Approved %'],
            [
                ['Questions (lms_question_master)', $r['totals']['questions']['total'], $r['totals']['questions']['tagged'], $r['totals']['questions']['coverage'] . '%', $r['totals']['questions']['approved'], $r['totals']['questions']['approved_coverage'] . '%'],
                ['Content (content_master)', $r['totals']['content']['total'], $r['totals']['content']['tagged'], $r['totals']['content']['coverage'] . '%', $r['totals']['content']['approved'], $r['totals']['content']['approved_coverage'] . '%'],
                ['Concepts (lms_concept)', $r['totals']['concepts']['total'], $r['totals']['concepts']['tagged'], $r['totals']['concepts']['coverage'] . '%', '—', '—'],
            ]
        );

        $this->line('');
        $this->comment('TOP TENANTS BY ESTATE SIZE');
        $this->table(
            ['sub_institute_id', 'Questions', 'Tagged', 'Content', 'Tagged'],
            array_map(fn ($t) => [
                $t['sub_institute_id'], $t['questions'], $t['questions_tagged'], $t['content'], $t['content_tagged'],
            ], $r['per_tenant'])
        );

        $this->line('');
        $this->comment("BLOOM DISTRIBUTION (tagged questions)");
        $bloom = [];
        foreach ($r['bloom_distribution'] as $k => $v) {
            $bloom[] = [$k, $v];
        }
        $this->table(['Bloom level', 'Questions'], $bloom);

        $this->line('');
        $this->comment('QA PIPELINE (spec §7.1)');
        foreach ($r['quality_pipeline'] as $estate => $counts) {
            $c5 = $counts['C5_machine_approved'];
            unset($counts['C5_machine_approved']);
            $this->line('  ' . str_pad($estate, 10) . collect($counts)->map(fn ($v, $k) => "{$k}={$v}")->implode('  '));
            if ($c5 > 0) {
                $this->error("  C5 VIOLATION: {$c5} {$estate} rows are 'approved' but were written by a machine.");
            }
        }

        $this->line('');
        $this->comment('MISCONCEPTION LIBRARY (CONTENT LAW C6)');
        $m = $r['misconceptions'];
        $this->line("  total={$m['total']}  approved={$m['approved']}  servable_with_corrective={$m['servable_with_corrective']}");
        if ($m['c6_pass']) {
            $this->line('  <fg=green>C6 PASS</> — every approved misconception has an approved corrective.');
        } else {
            $this->error("  C6 FAIL — {$m['c6_violations']} approved misconception(s) have no corrective: "
                . implode(', ', array_slice($m['c6_violation_tags'], 0, 10)));
        }

        $this->line('');
        $this->comment('IRT ELIGIBILITY (derived from history, not a pilot)');
        $i = $r['irt'];
        $this->line("  questions with >= {$i['min_responses']} responses: {$i['questions_irt_eligible']}"
            . "  ·  already derived: {$i['questions_already_derived']}  ·  remaining: {$i['remaining']}");
        $this->line("  questions with any response at all: {$i['questions_with_any_response']}");

        $this->line('');
        $this->comment('CURRICULUM LINKAGE — what the layer can actually route on');
        $l = $r['linkage'];
        $this->table(
            ['Estate', 'Total', 'concept_id', 'chapter_id', 'topic_id'],
            [
                ['Questions', $l['questions']['total'], $l['questions']['with_concept_id'], $l['questions']['with_chapter_id'], $l['questions']['with_topic_id']],
                ['Content', $l['content']['total'], $l['content']['with_concept_id'], $l['content']['with_chapter_id'], $l['content']['with_topic_id']],
            ]
        );
        $conceptShare = $l['questions']['total'] > 0
            ? $l['questions']['with_concept_id'] / $l['questions']['total'] : 0;
        if ($conceptShare < 0.5) {
            $this->warn('  concept_id is effectively unpopulated. The layer routes on chapter_ref_id until');
            $this->warn('  concept linkage is backfilled — see the P0.5 note in the master prompt doc.');
            $this->line("  lms_concept covers {$l['concepts']['chapters_covered_by_lms_concept']} chapters; "
                . "questions span {$l['concepts']['chapters_used_by_questions']}.");
        }

        $cj = $l['chapter_join'];
        $this->line("  chapter_id as a ROUTING key: {$cj['question_content_overlap']} chapters carry both "
            . 'questions and content — this is what the variant router joins on.');
        $this->line("  chapter_id as a DISPLAY label: only {$cj['question_chapters_named']} of "
            . "{$cj['question_chapters']} resolve to chapter_master ({$cj['chapter_master_rows']} rows).");
        if ($cj['question_chapters_named'] < $cj['question_chapters'] * 0.5) {
            $this->warn('  Chapter NAMES are largely unresolvable (the runbook R5 discrepancy). Routing is');
            $this->warn('  unaffected; teacher-facing screens will show ids until chapter_master is reconciled.');
        }

        $this->line('');
        $this->comment('INHERITABLE LMS TAGS (content_mapping_type)');
        $e = $r['existing_lms_tags'];
        $this->line("  content already carrying a Bloom mapping: {$e['content_with_bloom_mapping']}");
        $this->line("  content already carrying a pedagogy mapping: {$e['content_with_pedagogy_mapping']}");
        $this->line("  live pedagogy vocabulary size: {$e['pedagogy_vocabulary_size']}");

        if (isset($r['concept_gaps']) && $r['concept_gaps'] !== []) {
            $this->line('');
            $this->comment('TOP CHAPTERS BY LEARNER EXPOSURE — the P3/P4 work queue');
            $this->table(
                ['Chapter', 'Name', 'Responses', 'Questions', 'Tagged', 'Misconceptions', 'Gap to 3'],
                array_map(fn ($c) => [
                    $c['chapter_id'], mb_strimwidth((string) $c['name'], 0, 34, '…'),
                    number_format($c['responses']), $c['questions'], $c['questions_tagged'],
                    $c['misconceptions'], $c['misconception_gap'],
                ], $r['concept_gaps'])
            );
        }

        $this->line('');
        $this->line(str_repeat('─', 78));
    }

    protected function pct(int $n, int $total): float
    {
        return $total === 0 ? 0.0 : round($n / $total * 100, 2);
    }
}
