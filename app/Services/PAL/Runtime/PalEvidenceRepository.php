<?php

namespace App\Services\PAL\Runtime;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * PAL runtime — the real evidence source.
 *
 * The PAL V4 engines were written against the `pal_*` tables, which nothing
 * writes. The learner evidence PAL actually produces lives in the legacy LMS
 * stack, and this repository is the one place that knows how to read it:
 *
 *     lms_online_exam_answer   one row per response, ans_status right|wrong
 *       → lms_online_exam      one row per attempt, with timing and accuracy
 *       → question_paper       exam_type = 'PAL', paper_desc = chapter id
 *
 * Two facts about this estate shape everything downstream, and both were
 * measured rather than assumed:
 *
 *   1. NO PAL QUESTION CARRIES A concept_id. `lms_question_master.concept_id`
 *      is null for every question that has ever been answered, so the finest
 *      grain the evidence genuinely supports is the CHAPTER, taken from
 *      `question_paper.paper_desc`. Reporting per-concept mastery would mean
 *      inventing an attribution the data cannot support, so this repository
 *      returns chapter-grain units and names the concepts a chapter covers
 *      separately.
 *
 *   2. `question_paper.syear` is frequently null on PAL rows, so nothing here
 *      filters by academic year — a year filter would silently drop most of
 *      the evidence.
 *
 * Every query is guarded and returns empty rather than throwing: Administration
 * must render a truthful "no evidence" state on an estate that has none.
 */
class PalEvidenceRepository
{
    /** A response, in the order the learner gave it. */
    public const CORRECT = 'right';

    /** Per-request memo for responseSequences(); see the method for why. */
    private array $sequenceMemo = [];

    /**
     * Ordered responses per learner × chapter — the BKT input.
     *
     * @return array<string, array{
     *     learner_id:int, chapter_id:int, subject_id:?int, standard_id:?int,
     *     responses: array<int, array{correct:bool, at:string, question_id:?int, exam_id:int}>
     * }>
     */
    public function responseSequences(?int $tenant = null, ?int $learnerId = null): array
    {
        // Memoised for the life of the request: the Administration overview
        // computes all nine subsystems in one call and most of them start from
        // this same sequence set, so without this the estate is re-read nine
        // times per page load.
        $memoKey = ($tenant ?? 'all') . ':' . ($learnerId ?? 'all');
        if (isset($this->sequenceMemo[$memoKey])) {
            return $this->sequenceMemo[$memoKey];
        }

        if (! $this->tablesPresent()) {
            return $this->sequenceMemo[$memoKey] = [];
        }

        try {
            $query = DB::table('lms_online_exam_answer as a')
                ->join('lms_online_exam as e', 'e.id', '=', 'a.online_exam_id')
                ->join('question_paper as qp', 'qp.id', '=', 'e.question_paper_id')
                ->where('qp.exam_type', 'PAL')
                ->select([
                    'a.student_id',
                    'a.question_id',
                    'a.ans_status',
                    'a.created_at',
                    'a.online_exam_id',
                    'qp.paper_desc as chapter_id',
                    'qp.subject_id',
                    'qp.standard_id',
                ])
                ->orderBy('a.student_id')
                ->orderBy('qp.paper_desc')
                // Chronological within a unit: BKT is order-dependent, so this
                // ordering is part of the correctness of the model, not a nicety.
                ->orderBy('a.created_at')
                ->orderBy('a.id');

            if ($tenant !== null) {
                $query->where('qp.sub_institute_id', $tenant);
            }
            if ($learnerId !== null) {
                $query->where('a.student_id', $learnerId);
            }

            $rows = $query->limit(20000)->get();
        } catch (Throwable) {
            return $this->sequenceMemo[$memoKey] = [];
        }

        $units = [];
        foreach ($rows as $row) {
            $chapterId = (int) $row->chapter_id;
            $learner = (int) $row->student_id;
            $key = $learner . ':' . $chapterId;

            if (! isset($units[$key])) {
                $units[$key] = [
                    'learner_id' => $learner,
                    'chapter_id' => $chapterId,
                    'subject_id' => $row->subject_id === null ? null : (int) $row->subject_id,
                    'standard_id' => $row->standard_id === null ? null : (int) $row->standard_id,
                    'responses' => [],
                ];
            }

            $units[$key]['responses'][] = [
                'correct' => strtolower(trim((string) $row->ans_status)) === self::CORRECT,
                'at' => (string) $row->created_at,
                'question_id' => $row->question_id === null ? null : (int) $row->question_id,
                'exam_id' => (int) $row->online_exam_id,
            ];
        }

        return $this->sequenceMemo[$memoKey] = $units;
    }

    /**
     * Per-attempt metrics the legacy exam row already computes.
     *
     * `avg_time` and `accuracy_rate` are nullable — older PAL attempts predate
     * those columns being populated — so callers must treat a null as "not
     * measured" rather than zero.
     *
     * @return array<int, array{exam_id:int, learner_id:int, chapter_id:int, right:int, wrong:int, accuracy:?float, avg_time:?float, struggle:?float, started_at:?string}>
     */
    public function attempts(?int $tenant = null, ?int $learnerId = null): array
    {
        if (! $this->tablesPresent()) {
            return [];
        }

        try {
            $query = DB::table('lms_online_exam as e')
                ->join('question_paper as qp', 'qp.id', '=', 'e.question_paper_id')
                ->where('qp.exam_type', 'PAL')
                ->select([
                    'e.id', 'e.student_id', 'e.total_right', 'e.total_wrong',
                    'e.accuracy_rate', 'e.avg_time', 'e.struggle_score', 'e.start_time',
                    'qp.paper_desc as chapter_id',
                ])
                ->orderByDesc('e.id');

            if ($tenant !== null) {
                $query->where('qp.sub_institute_id', $tenant);
            }
            if ($learnerId !== null) {
                $query->where('e.student_id', $learnerId);
            }

            $rows = $query->limit(5000)->get();
        } catch (Throwable) {
            return [];
        }

        return array_map(static fn ($row) => [
            'exam_id' => (int) $row->id,
            'learner_id' => (int) $row->student_id,
            'chapter_id' => (int) $row->chapter_id,
            'right' => (int) $row->total_right,
            'wrong' => (int) $row->total_wrong,
            'accuracy' => $row->accuracy_rate === null ? null : (float) $row->accuracy_rate,
            'avg_time' => $row->avg_time === null ? null : (float) $row->avg_time,
            'struggle' => $row->struggle_score === null ? null : (float) $row->struggle_score,
            'started_at' => $row->start_time === null ? null : (string) $row->start_time,
        ], $rows->all());
    }

    /** Human names for the chapters that appear in the evidence. */
    public function chapterNames(array $chapterIds): array
    {
        $chapterIds = array_values(array_unique(array_filter($chapterIds)));
        if ($chapterIds === [] || ! Schema::hasTable('chapter_master')) {
            return [];
        }

        try {
            return DB::table('chapter_master')
                ->whereIn('id', $chapterIds)
                ->pluck('chapter_name', 'id')
                ->map(static fn ($name) => (string) $name)
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Concepts a chapter covers, with their PAL V4 metadata.
     *
     * The mastery gate is per concept (`pal_concept_metadata.mastery_gate`) and
     * overrides the estate-wide BKT threshold when present — that is the whole
     * point of tagging a concept.
     *
     * @return array<int, array{id:int, name:string, mastery_gate:?float, bloom_ceiling:?string, priority:?int, riasec:?string, gardner:?string, ncdg:?string}>
     */
    public function conceptsForChapters(array $chapterIds, ?int $tenant = null): array
    {
        $chapterIds = array_values(array_unique(array_filter($chapterIds)));
        if ($chapterIds === [] || ! Schema::hasTable('lms_concept')) {
            return [];
        }

        try {
            $query = DB::table('lms_concept as c')->whereIn('c.chapter_id', $chapterIds);

            if (Schema::hasTable('pal_concept_metadata')) {
                $query->leftJoin('pal_concept_metadata as m', 'm.concept_ref_id', '=', 'c.id')
                    ->select([
                        'c.id', 'c.name', 'c.chapter_id',
                        'm.mastery_gate', 'm.bloom_ceiling', 'm.priority_score',
                        'm.riasec_primary', 'm.gardner_primary', 'm.ncdg_goal',
                    ]);
            } else {
                $query->select(['c.id', 'c.name', 'c.chapter_id']);
            }

            if ($tenant !== null && Schema::hasColumn('lms_concept', 'sub_institute_id')) {
                $query->where('c.sub_institute_id', $tenant);
            }

            $rows = $query->limit(2000)->get();
        } catch (Throwable) {
            return [];
        }

        return array_map(static fn ($row) => [
            'id' => (int) $row->id,
            'chapter_id' => (int) ($row->chapter_id ?? 0),
            'name' => (string) $row->name,
            'mastery_gate' => isset($row->mastery_gate) && $row->mastery_gate !== null ? (float) $row->mastery_gate : null,
            'bloom_ceiling' => $row->bloom_ceiling ?? null,
            'priority' => isset($row->priority_score) && $row->priority_score !== null ? (int) $row->priority_score : null,
            'riasec' => $row->riasec_primary ?? null,
            'gardner' => $row->gardner_primary ?? null,
            'ncdg' => $row->ncdg_goal ?? null,
        ], $rows->all());
    }

    /**
     * Career-signal tallies across every tagged concept on the estate.
     *
     * @return array{riasec: array<string,int>, gardner: array<string,int>, ncdg: array<string,int>, tagged: int, total: int}
     */
    public function careerSignalCoverage(?int $tenant = null): array
    {
        $empty = ['riasec' => [], 'gardner' => [], 'ncdg' => [], 'tagged' => 0, 'total' => 0];

        if (! Schema::hasTable('pal_concept_metadata')) {
            return $empty;
        }

        try {
            $query = DB::table('pal_concept_metadata');
            if ($tenant !== null) {
                $query->where('sub_institute_id', $tenant);
            }

            $rows = $query->select(['riasec_primary', 'gardner_primary', 'ncdg_goal'])->limit(20000)->get();
        } catch (Throwable) {
            return $empty;
        }

        $out = $empty;
        $out['total'] = $rows->count();

        foreach ($rows as $row) {
            $any = false;
            foreach ([['riasec', $row->riasec_primary], ['gardner', $row->gardner_primary], ['ncdg', $row->ncdg_goal]] as [$group, $value]) {
                $value = trim((string) ($value ?? ''));
                if ($value === '') {
                    continue;
                }
                $out[$group][$value] = ($out[$group][$value] ?? 0) + 1;
                $any = true;
            }
            if ($any) {
                $out['tagged']++;
            }
        }

        foreach (['riasec', 'gardner', 'ncdg'] as $group) {
            arsort($out[$group]);
        }

        return $out;
    }

    /**
     * The prerequisite graph, read out of the extracted chapter intelligence.
     *
     * `semantic_intelligence.prerequisites` is the only prerequisite data this
     * estate holds; Neo4j carries no :Concept DAG. Edges are name-based, so an
     * edge whose target does not resolve to a known concept is reported as
     * DANGLING rather than silently dropped — an unresolvable prerequisite is
     * exactly what a content lead needs to see.
     *
     * @return array{nodes:int, edges:int, dangling:int, roots:int, chapters:int, sample: array<int, array{from:string,to:string,resolved:bool}>}
     */
    public function prerequisiteGraph(?int $tenant = null): array
    {
        $empty = ['nodes' => 0, 'edges' => 0, 'dangling' => 0, 'roots' => 0, 'chapters' => 0, 'sample' => []];

        if (! Schema::hasTable('semantic_intelligence')) {
            return $empty;
        }

        try {
            $query = DB::table('semantic_intelligence');
            if ($tenant !== null && Schema::hasColumn('semantic_intelligence', 'sub_institute_id')) {
                $query->where('sub_institute_id', $tenant);
            }

            $blobColumn = Schema::hasColumn('semantic_intelligence', 'full_intelegance_json')
                ? 'full_intelegance_json'
                : 'full_intelligence_json';

            $select = ['id', $blobColumn . ' as blob'];
            if (Schema::hasColumn('semantic_intelligence', 'prerequisites')) {
                $select[] = 'prerequisites';
            }

            $rows = $query->select($select)->limit(500)->get();
        } catch (Throwable) {
            return $empty;
        }

        $concepts = [];
        $edges = [];

        foreach ($rows as $row) {
            // Node names come from the blob's concept list. The extractor calls
            // the name field `concept`, not `name`.
            $decoded = json_decode((string) $row->blob, true);
            if (is_array($decoded)) {
                foreach ($this->extractConcepts($decoded) as $concept) {
                    $name = $this->conceptName($concept);
                    if ($name !== '') {
                        $concepts[$name] = true;
                    }
                }
            }

            // Edges come from the denormalised `prerequisites` column, whose
            // entries carry `_parent_concept` — the concept that REQUIRES the
            // named prerequisite. Without that attribution an edge has no
            // target, so it is counted as unattributed rather than guessed at.
            foreach ($this->decodeList($row->prerequisites ?? null) as $prerequisite) {
                if (! is_array($prerequisite)) {
                    continue;
                }

                $from = $this->normaliseName($prerequisite['concept_name'] ?? '');
                $to = $this->normaliseName($prerequisite['_parent_concept'] ?? '');

                if ($from === '' || $to === '' || $from === $to) {
                    continue;
                }

                $edges[] = ['from' => $from, 'to' => $to];
            }
        }

        // De-duplicate: the extractor repeats a prerequisite once per necessity
        // level, and a repeated edge is the same edge.
        $unique = [];
        foreach ($edges as $edge) {
            $unique[$edge['from'] . '→' . $edge['to']] = $edge;
        }
        $edges = array_values($unique);

        $dangling = 0;
        $withIncoming = [];
        $sample = [];

        foreach ($edges as $edge) {
            $resolved = isset($concepts[$edge['from']]);
            if (! $resolved) {
                $dangling++;
            } else {
                $withIncoming[$edge['to']] = true;
            }
            if (count($sample) < 12) {
                $sample[] = ['from' => $edge['from'], 'to' => $edge['to'], 'resolved' => $resolved];
            }
        }

        // The resolved subset is the DAG that could actually be projected into
        // a graph database: both endpoints are known concepts. Dangling edges
        // are counted for the health figure but cannot be drawn, because one
        // end of them does not exist.
        $resolved = [];
        foreach ($edges as $edge) {
            if (isset($concepts[$edge['from']])) {
                $resolved[] = $edge;
            }
        }

        return [
            'nodes' => count($concepts),
            'edges' => count($edges),
            'dangling' => $dangling,
            'roots' => max(0, count($concepts) - count($withIncoming)),
            'chapters' => $rows->count(),
            'sample' => $sample,
            'node_names' => array_keys($concepts),
            'resolved_edges' => $resolved,
        ];
    }

    /**
     * Numeric grade for each standard id, for the stage rollup.
     *
     * `tblstudent` carries neither `user_id` nor `standard` on this estate, so
     * the learner record cannot supply a grade. The PAL paper can: every
     * `question_paper` row names the `standard_id` it was generated for, and
     * `standard.name` holds the grade as a plain number ("6", "7"). That is a
     * stronger signal anyway — it is the grade the learner was actually
     * assessed at, not the class they are currently enrolled in.
     *
     * A standard whose name is not numeric (e.g. "CBSE-JR") yields no grade
     * rather than 0, which would wrongly place it in the Foundational stage.
     *
     * @return array<int,int> standard_id => grade
     */
    public function gradesForStandards(array $standardIds): array
    {
        $standardIds = array_values(array_unique(array_filter($standardIds)));
        if ($standardIds === [] || ! Schema::hasTable('standard')) {
            return [];
        }

        try {
            $rows = DB::table('standard')->whereIn('id', $standardIds)->get(['id', 'name']);
        } catch (Throwable) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $name = trim((string) $row->name);
            if (preg_match('/^\d{1,2}$/', $name) === 1) {
                $out[(int) $row->id] = (int) $name;
            }
        }

        return $out;
    }

    /** Chapters per grade band, for stage coverage. */
    public function chaptersByGrade(int $from, int $to, ?int $tenant = null): int
    {
        if (! Schema::hasTable('semantic_intelligence') || ! Schema::hasColumn('semantic_intelligence', 'standard')) {
            return 0;
        }

        try {
            $query = DB::table('semantic_intelligence')->whereBetween('standard', [$from, $to]);
            if ($tenant !== null && Schema::hasColumn('semantic_intelligence', 'sub_institute_id')) {
                $query->where('sub_institute_id', $tenant);
            }

            return (int) $query->count();
        } catch (Throwable) {
            return 0;
        }
    }

    public function tablesPresent(): bool
    {
        return Schema::hasTable('lms_online_exam_answer')
            && Schema::hasTable('lms_online_exam')
            && Schema::hasTable('question_paper');
    }

    // ── internals ────────────────────────────────────────────────────────

    /** The extractor nests concepts under a few different keys by model version. */
    private function extractConcepts(array $decoded): array
    {
        foreach (['concepts', 'key_concepts', 'concept_list'] as $key) {
            if (isset($decoded[$key]) && is_array($decoded[$key])) {
                return array_filter($decoded[$key], 'is_array');
            }
        }

        foreach ($decoded as $value) {
            if (is_array($value) && isset($value['concepts']) && is_array($value['concepts'])) {
                return array_filter($value['concepts'], 'is_array');
            }
        }

        return [];
    }

    /**
     * The concept's display name.
     *
     * The extractor emits the name under `concept`; older rows use `name` or
     * `concept_name`. Trying all three keeps the graph working across model
     * versions rather than silently returning an empty node set.
     */
    private function conceptName(array $concept): string
    {
        foreach (['concept', 'name', 'concept_name', 'title'] as $key) {
            $value = $this->normaliseName($concept[$key] ?? '');
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    /** A JSON column that holds a list; anything else yields an empty list. */
    private function decodeList(mixed $raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }

        $decoded = json_decode((string) $raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Concept names are free text from an LLM; compare them case- and
     * space-insensitively.
     *
     * The extractor sometimes emits a nested object instead of a string
     * (`{"concept_name": {"name": "..."}}`) or a list of names. Unwrap one
     * level rather than letting a string cast blow up mid-projection; anything
     * still not scalar yields '' and is skipped by the caller.
     */
    private function normaliseName(mixed $value): string
    {
        if (is_array($value)) {
            foreach (['concept_name', 'concept', 'name', 'title'] as $key) {
                if (isset($value[$key]) && is_scalar($value[$key])) {
                    $value = $value[$key];
                    break;
                }
            }

            if (is_array($value)) {
                $first = reset($value);
                $value = is_scalar($first) ? $first : '';
            }
        }

        if (! is_scalar($value)) {
            return '';
        }

        $text = strtolower(trim((string) $value));

        return preg_replace('/\s+/', ' ', $text) ?? '';
    }
}
