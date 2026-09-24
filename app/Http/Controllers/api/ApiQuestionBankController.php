<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Browsing surface for the shared board-level question bank.
 *
 * ApiLmsCourseController@getQuestionBank stays exactly as it is: it serves the
 * course-master editor, takes one chapter, and collapses every form to
 * MCQ|Narrative. This controller is additive and answers a different question
 * -- "show me every question across the board, filtered" -- which needs the
 * richer vocabulary that extraction now records in lms_question_extraction.
 *
 * sub_institute_id here is a BOARD id for shared banks (1 = CBSE,
 * 341 = Cambridge), not a school id.
 */
class ApiQuestionBankController extends Controller
{
    /** Page sizes above this make the options sub-query expensive for no gain. */
    private const MAX_PER_PAGE = 100;

    /**
     * Whether the extraction sidecar exists in this database.
     *
     * The table is created by the extraction service, not by a Laravel
     * migration, so an environment that has never run an extraction simply
     * has no sidecar. Everything still works there -- the bank just falls
     * back to the plain lms_question_master columns.
     */
    private function hasSidecar(): bool
    {
        static $has = null;

        if ($has === null) {
            $has = Schema::hasTable('lms_question_extraction');
        }

        return $has;
    }

    /**
     * GET /api/question-bank/filters
     *
     * Every dropdown the bank page renders, derived from what is actually
     * stored rather than from a hardcoded list -- a publisher's own question
     * form appears here the moment the first question using it is ingested.
     */
    /**
     * The scope every facet count is measured within.
     *
     * Without this each dropdown counted the whole estate, so a chapter with
     * four "Analyze" questions offered "Analyze (27)". A count that does not
     * match what the filter will return is worse than no count at all.
     *
     * `$except` drops one dimension so a facet does not narrow itself: the
     * chapter list must not be filtered by the chosen chapter, or picking one
     * leaves a dropdown with a single entry and no way back.
     */
    private function scoped($query, Request $request, string $prefix = 'q.', string $except = ''): void
    {
        foreach ([
            'sub_institute_id' => 'sub_institute_id',
            'standard_id' => 'standard_id',
            'subject_id' => 'subject_id',
            'chapter_id' => 'chapter_id',
        ] as $param => $column) {
            if ($param === $except) {
                continue;
            }
            $value = $request->input($param);
            if ($value !== null && $value !== '' && strtolower((string) $value) !== 'all') {
                $query->where($prefix . $column, (int) $value);
            }
        }
    }

    public function filters(Request $request): JsonResponse
    {
        try {
            $board = $request->input('sub_institute_id');

            $boards = $this->boardOptions();

            $standards = DB::table('lms_question_master as q')
                ->join('standard as st', 'st.id', '=', 'q.standard_id')
                ->whereNull('q.deleted_at')
                ->tap(fn ($w) => $this->scoped($w, $request, 'q.', 'standard_id'))
                ->select('st.id', 'st.name', DB::raw('COUNT(*) as total'))
                ->groupBy('st.id', 'st.name', 'st.sort_order')
                ->orderBy('st.sort_order')
                ->get();

            $subjects = DB::table('lms_question_master as q')
                ->join('subject as sb', 'sb.id', '=', 'q.subject_id')
                ->whereNull('q.deleted_at')
                ->tap(fn ($w) => $this->scoped($w, $request, 'q.', 'subject_id'))
                ->select('sb.id', 'sb.subject_name as name', DB::raw('COUNT(*) as total'))
                ->groupBy('sb.id', 'sb.subject_name')
                ->orderBy('sb.subject_name')
                ->get();

            $chapters = DB::table('lms_question_master as q')
                ->join('chapter_master as ch', 'ch.id', '=', 'q.chapter_id')
                ->whereNull('q.deleted_at')
                ->tap(fn ($w) => $this->scoped($w, $request, 'q.', 'chapter_id'))
                ->select('ch.id', 'ch.chapter_name as name', DB::raw('COUNT(*) as total'))
                ->groupBy('ch.id', 'ch.chapter_name', 'ch.sort_order')
                ->orderBy('ch.sort_order')
                ->get();

            $concepts = DB::table('lms_question_master as q')
                ->join('lms_concept as c', 'c.id', '=', 'q.concept_id')
                ->whereNull('q.deleted_at')
                ->tap(fn ($w) => $this->scoped($w, $request))
                ->select('c.id', 'c.name', DB::raw('COUNT(*) as total'))
                ->groupBy('c.id', 'c.name')
                ->orderBy('c.name')
                ->get();

            return response()->json([
                'status' => true,
                'message' => 'Filters fetched successfully.',
                'data' => [
                    'boards' => $boards,
                    'standards' => $standards,
                    'subjects' => $subjects,
                    'chapters' => $chapters,
                    'concepts' => $concepts,
                    'question_types' => $this->questionTypeOptions($request),
                    'publishers' => $this->publisherOptions(),
                    // g_bloom is Title-Case on most rows but a handful of older
                    // rows stored it lowercase, so fold before offering it as a
                    // choice or the dropdown shows "apply" and "Apply" twice.
                    'bloom_levels' => $this->distinctFolded('g_bloom', $request),
                    'difficulty_levels' => $this->distinctFolded('g_difficulty', $request),
                    'dok_levels' => DB::table('lms_question_master')
                        ->whereNull('deleted_at')
                        ->whereNotNull('g_dok')
                        ->tap(fn ($w) => $this->scoped($w, $request, ''))
                        ->select('g_dok as value', DB::raw('COUNT(*) as total'))
                        ->groupBy('g_dok')
                        ->orderBy('g_dok')
                        ->get(),
                    'exam_sections' => $this->hasSidecar()
                        ? DB::table('lms_question_extraction as x')
                            ->join('lms_question_master as q', 'q.id', '=', 'x.question_id')
                            ->whereNotNull('x.exam_section')
                            ->whereNull('q.deleted_at')
                            ->tap(fn ($w) => $this->scoped($w, $request))
                            ->select('x.exam_section as value', DB::raw('COUNT(*) as total'))
                            ->groupBy('x.exam_section')
                            ->orderBy('x.exam_section')
                            ->get()
                        : [],
                ],
            ], 200);
        } catch (Throwable $e) {
            Log::error('question-bank filters failed', ['error' => $e->getMessage()]);

            return response()->json([
                'status' => false,
                'message' => 'Failed to load filters: ' . $e->getMessage(),
                'data' => [],
            ], 500);
        }
    }

    /**
     * GET|POST /api/question-bank/question-types
     *
     * Just the question-form vocabulary, for a type dropdown. Split out from
     * filters() so the course-master bank can populate one select without
     * paying for eleven facet queries it will not use.
     */
    public function questionTypes(Request $request): JsonResponse
    {
        try {
            return response()->json([
                'status' => true,
                'message' => 'Question types fetched successfully.',
                'data' => $this->questionTypeOptions($request),
            ], 200);
        } catch (Throwable $e) {
            Log::error('question-bank question-types failed', ['error' => $e->getMessage()]);

            return response()->json([
                'status' => false,
                'message' => 'Failed to load question types: ' . $e->getMessage(),
                'data' => [],
            ], 500);
        }
    }

    /**
     * Boards that actually hold questions.
     *
     * For a shared content bank sub_institute_id names a BOARD, not a school:
     * 1 is CBSE and 341 is Cambridge. school_setup stores the *school* that
     * happens to own those ids ("Triz International School"), so labelling
     * the board picker from it would be wrong. Name the boards we know and
     * fall back to the school name for every other tenant, which is a real
     * school bank rather than a board one.
     */
    private const BOARD_NAMES = [
        1 => 'CBSE',
        341 => 'Cambridge',
    ];

    private function boardOptions(): array
    {
        $counts = DB::table('lms_question_master')
            ->whereNull('deleted_at')
            ->select('sub_institute_id as id', DB::raw('COUNT(*) as total'))
            ->groupBy('sub_institute_id')
            ->orderByDesc('total')
            ->get();

        $names = DB::table('school_setup')
            ->whereIn('Id', $counts->pluck('id')->all() ?: [0])
            ->pluck('SchoolName', 'Id');

        $out = [];
        foreach ($counts as $row) {
            $id = (int) $row->id;
            $out[] = [
                'id' => $id,
                'name' => self::BOARD_NAMES[$id] ?? ($names[$id] ?? ('Tenant ' . $id)),
                'is_board' => isset(self::BOARD_NAMES[$id]),
                'total' => (int) $row->total,
            ];
        }

        return $out;
    }

    /**
     * Distinct values of a g_* column, case-folded so one spelling wins.
     */
    private function distinctFolded(string $column, Request $request): array
    {
        $rows = DB::table('lms_question_master')
            ->whereNull('deleted_at')
            ->whereNotNull($column)
            ->where($column, '<>', '')
            ->tap(fn ($w) => $this->scoped($w, $request, ''))
            ->select($column . ' as value', DB::raw('COUNT(*) as total'))
            ->groupBy($column)
            ->get();

        $merged = [];
        foreach ($rows as $row) {
            $key = strtolower(trim((string) $row->value));
            if ($key === '') {
                continue;
            }
            if (!isset($merged[$key])) {
                $merged[$key] = ['value' => ucfirst($key), 'total' => 0];
            }
            $merged[$key]['total'] += (int) $row->total;
        }

        return array_values($merged);
    }

    /**
     * The question forms the bank can filter on.
     *
     * question_type_catalog is the rich vocabulary (MCQ, assertion-reason,
     * case study, plus whatever a publisher invented). question_type_master is
     * the grading engine's eight rows. Prefer the catalogue and fall back, so
     * this endpoint is useful before any extraction has run.
     */
    private function questionTypeOptions(Request $request): array
    {
        if (!Schema::hasTable('question_type_catalog') || !$this->hasSidecar()) {
            return DB::table('question_type_master')
                ->where('status', 1)
                ->select('question_type as code', 'question_type as label')
                ->distinct()
                ->orderBy('question_type')
                ->get()
                ->toArray();
        }

        // Count on the question's EFFECTIVE form. An AI-generated row has no
        // extraction sidecar and therefore no catalogue code, so counting the
        // sidecar alone reported "Multiple Choice (0)" on a chapter holding
        // 125 MCQs. Fall back to the grading type for those.
        $countRows = DB::table('lms_question_master as q')
            ->leftJoin('lms_question_extraction as x', 'x.question_id', '=', 'q.id')
            ->whereNull('q.deleted_at')
            ->tap(fn ($w) => $this->scoped($w, $request))
            ->select(
                DB::raw(
                    // Four tiers, most authoritative first. The sidecar code was
                    // read off the source document. question_format_code is a
                    // teacher's own manual pick. q.g_qtype_code is derived from
                    // the stem for the ~3k questions that were generated and so
                    // have no sidecar -- without it every one of them counted as
                    // "narrative" and the form dropdown could not see them at all.
                    "COALESCE(x.question_type_code, q.question_format_code, q.g_qtype_code, "
                    . "CASE WHEN q.question_type_id = 1 "
                    . "THEN 'mcq' ELSE 'narrative' END) as code"
                ),
                DB::raw('COUNT(*) as total'),
                // Lets a catalogue-less code (below) still carry a real
                // lms_question_type_id instead of leaving the Format dropdown
                // unable to filter it under either Question Type.
                DB::raw('MAX(q.question_type_id) as qtype_id')
            )
            ->groupBy('code')
            ->get();
        $counts = $countRows->pluck('total', 'code');
        $qtypeIdByCode = $countRows->pluck('qtype_id', 'code');

        // LEFT from the catalogue, so every known form is listed even at zero:
        // a dropdown whose contents change per chapter is the thing being
        // fixed here.
        $catalogue = DB::table('question_type_catalog as t')
            ->leftJoin('question_publisher as p', 'p.id', '=', 't.publisher_id')
            ->where('t.status', 1)
            ->select('t.code', 't.label', 't.exam_section', 't.default_marks',
                     't.is_standard', 't.lms_question_type_id', 'p.short_name as publisher')
            ->orderByDesc('t.is_standard')
            ->orderBy('t.label')
            ->get();

        $out = [];
        $seen = [];
        foreach ($catalogue as $row) {
            $seen[$row->code] = true;
            $out[] = [
                'code' => $row->code,
                'label' => $row->label,
                'exam_section' => $row->exam_section,
                'default_marks' => $row->default_marks,
                'is_standard' => $row->is_standard,
                'lms_question_type_id' => $row->lms_question_type_id !== null
                    ? (int) $row->lms_question_type_id
                    : null,
                'publisher' => $row->publisher,
                'total' => (int) ($counts[$row->code] ?? 0),
            ];
        }

        // A code in use that the catalogue has never seen still has to be
        // reachable, or those questions cannot be filtered to at all.
        foreach ($counts as $code => $total) {
            if (isset($seen[$code])) {
                continue;
            }
            $out[] = [
                'code' => $code,
                'label' => ucwords(str_replace('_', ' ', (string) $code)),
                'exam_section' => null,
                'default_marks' => null,
                'is_standard' => 0,
                'lms_question_type_id' => isset($qtypeIdByCode[$code]) && $qtypeIdByCode[$code] !== null
                    ? (int) $qtypeIdByCode[$code]
                    : null,
                'publisher' => null,
                'total' => (int) $total,
            ];
        }

        return $out;
    }

    private function publisherOptions(): array
    {
        if (!Schema::hasTable('question_publisher')) {
            return [];
        }

        return DB::table('question_publisher as p')
            ->leftJoin('lms_question_extraction as x', 'x.publisher_id', '=', 'p.id')
            ->where('p.status', 1)
            ->select('p.id', 'p.code', 'p.name', 'p.short_name', 'p.licence_type', DB::raw('COUNT(x.id) as total'))
            ->groupBy('p.id', 'p.code', 'p.name', 'p.short_name', 'p.licence_type')
            ->orderByDesc('total')
            ->get()
            ->toArray();
    }

    /**
     * POST /api/question-bank/search
     *
     * Paginated, filtered question list. Every filter is optional; with none
     * supplied this returns the whole bank newest-first.
     */
    public function search(Request $request): JsonResponse
    {
        try {
            $perPage = min(max((int) $request->input('per_page', 20), 1), self::MAX_PER_PAGE);
            $page = max((int) $request->input('page', 1), 1);
            $sidecar = $this->hasSidecar();

            $query = DB::table('lms_question_master as q')
                ->leftJoin('question_type_master as qt', 'qt.id', '=', 'q.question_type_id')
                ->leftJoin('chapter_master as ch', 'ch.id', '=', 'q.chapter_id')
                ->leftJoin('lms_concept as c', 'c.id', '=', 'q.concept_id')
                ->leftJoin('standard as st', 'st.id', '=', 'q.standard_id')
                ->leftJoin('subject as sb', 'sb.id', '=', 'q.subject_id')
                ->whereNull('q.deleted_at');

            if ($sidecar) {
                // LEFT so AI-generated questions, which have no sidecar row,
                // stay visible alongside extracted ones.
                $query->leftJoin('lms_question_extraction as x', 'x.question_id', '=', 'q.id')
                    ->leftJoin('question_publisher as p', 'p.id', '=', 'x.publisher_id');
            }

            $this->applyFilters($query, $request, $sidecar);

            $total = (clone $query)->count('q.id');

            $select = [
                'q.id', 'q.chapter_id', 'q.concept_id', 'q.concept as concept_text',
                'q.standard_id', 'q.subject_id', 'q.question_title', 'q.description',
                'q.points as marks', 'q.category', 'q.status', 'q.answer as answer_envelope',
                'q.g_bloom as bloom', 'q.g_dok as dok', 'q.g_difficulty as difficulty',
                'q.g_qtype_code as derived_type_code',
                'q.question_type_id',
                'qt.question_type as lms_question_type',
                'ch.chapter_name', 'c.name as concept_name',
                'st.name as standard_name', 'sb.subject_name',
            ];

            if ($sidecar) {
                $select = array_merge($select, [
                    'x.question_type_code', 'x.exam_section', 'x.item_number',
                    'x.section_marks', 'x.attribution', 'x.licence',
                    'x.validation_status', 'x.figure_required', 'x.figure_resolved',
                    'x.reproduction', 'x.extraction_id',
                    'x.concept_confidence', 'x.ai_model',
                    'p.name as publisher_name', 'p.short_name as publisher_short',
                    'p.code as publisher_code',
                ]);
            }

            $rows = $query->select($select)
                ->orderByDesc('q.id')
                ->forPage($page, $perPage)
                ->get();

            $ids = $rows->pluck('id')->all();

            return response()->json([
                'status' => true,
                'message' => 'Questions fetched successfully.',
                'data' => $this->shape($rows, $this->optionsFor($ids), $this->figuresFor($ids, $sidecar)),
                'meta' => [
                    'page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'last_page' => (int) ceil(max($total, 1) / $perPage),
                    'sidecar_available' => $sidecar,
                ],
            ], 200);
        } catch (Throwable $e) {
            Log::error('question-bank search failed', ['error' => $e->getMessage()]);

            return response()->json([
                'status' => false,
                'message' => 'Search failed: ' . $e->getMessage(),
                'data' => [],
            ], 500);
        }
    }

    /**
     * Every filter the bank page exposes. All optional, all ANDed.
     */
    private function applyFilters($query, Request $request, bool $sidecar): void
    {
        foreach ([
            'sub_institute_id' => 'q.sub_institute_id',
            'standard_id' => 'q.standard_id',
            'subject_id' => 'q.subject_id',
            'chapter_id' => 'q.chapter_id',
            'concept_id' => 'q.concept_id',
            'question_type_id' => 'q.question_type_id',
            'marks' => 'q.points',
            'g_dok' => 'q.g_dok',
        ] as $param => $column) {
            $value = $request->input($param);
            if ($value !== null && $value !== '' && strtolower((string) $value) !== 'all') {
                $query->where($column, is_numeric($value) ? (int) $value : $value);
            }
        }

        if ($request->filled('category') && strtolower($request->input('category')) !== 'all') {
            $query->where('q.category', trim($request->input('category')));
        }

        // Case-insensitive: the estate holds both "Apply" and "apply".
        foreach (['bloom' => 'q.g_bloom', 'difficulty' => 'q.g_difficulty'] as $param => $column) {
            if ($request->filled($param) && strtolower($request->input($param)) !== 'all') {
                $query->whereRaw("LOWER($column) = ?", [strtolower(trim($request->input($param)))]);
            }
        }

        // "Only questions with/without a concept" -- the single most useful
        // quality filter, because 61k of 62k rows have never been mapped.
        if ($request->filled('has_concept')) {
            $request->boolean('has_concept')
                ? $query->whereNotNull('q.concept_id')
                : $query->whereNull('q.concept_id');
        }

        $status = strtolower((string) $request->input('status', 'all'));
        if ($status === 'published') {
            $query->where('q.status', 1);
        } elseif ($status === 'held') {
            $query->where('q.status', 0);
        }

        if ($request->filled('search')) {
            $term = '%' . str_replace(['%', '_'], ['\%', '\_'], trim($request->input('search'))) . '%';
            $query->where(function ($w) use ($term) {
                $w->where('q.question_title', 'like', $term)
                    ->orWhere('q.description', 'like', $term);
            });
        }

        if (!$sidecar) {
            return;
        }

        foreach ([
            'publisher_id' => 'x.publisher_id',
            'extraction_id' => 'x.extraction_id',
            'exam_section' => 'x.exam_section',
        ] as $param => $column) {
            $value = $request->input($param);
            if ($value !== null && $value !== '' && strtolower((string) $value) !== 'all') {
                $query->where($column, is_numeric($value) ? (int) $value : $value);
            }
        }

        // The rich form vocabulary. Accepts one code or a list, so the UI can
        // offer multi-select without a second endpoint.
        $codes = $request->input('question_type_code');
        if (is_string($codes) && $codes !== '' && strtolower($codes) !== 'all') {
            $codes = [$codes];
        }
        if (is_array($codes) && $codes !== []) {
            // Match on the same three-tier resolution the counts use, or a
            // chapter would offer "Very Short Answer (990)" and then return
            // nothing when you picked it.
            $query->whereIn(
                DB::raw(
                    "COALESCE(x.question_type_code, q.g_qtype_code, "
                    . "CASE WHEN q.question_type_id = 1 THEN 'mcq' ELSE 'narrative' END)"
                ),
                $codes
            );
        }

        // Provenance: extracted from a published book, or machine-generated.
        $source = strtolower((string) $request->input('source', 'all'));
        if ($source === 'extracted') {
            $query->whereNotNull('x.id');
        } elseif ($source === 'ai_generated') {
            $query->whereNull('x.id');
        }
    }

    /**
     * Options for a page of questions, labelled A.. by stored row order.
     *
     * Matches getQuestionBank's labelling so the same question reads
     * identically in the editor and in the bank.
     */
    private function optionsFor(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $byQuestion = [];
        $rows = DB::table('answer_master')
            ->whereIn('question_id', $ids)
            ->orderBy('id')
            ->get(['question_id', 'answer', 'correct_answer']);

        foreach ($rows as $row) {
            $qid = (int) $row->question_id;
            $index = count($byQuestion[$qid] ?? []);
            $byQuestion[$qid][] = [
                'label' => chr(65 + $index),
                'text' => (string) $row->answer,
                'is_correct' => (bool) $row->correct_answer,
            ];
        }

        return $byQuestion;
    }

    /**
     * Figures attached to a page of questions.
     *
     * stored_url is absolute and points at the extraction service, so a page
     * that cannot reach that host renders the caption and OCR text instead of
     * a broken image -- which is why ocr_text is returned alongside the URL.
     */
    private function figuresFor(array $ids, bool $sidecar): array
    {
        if ($ids === [] || !$sidecar || !Schema::hasTable('lms_question_asset')) {
            return [];
        }

        $byQuestion = [];
        $rows = DB::table('lms_question_asset')
            ->whereIn('question_id', $ids)
            ->orderBy('ordinal')
            ->get(['question_id', 'stored_url', 'asset_sha256', 'width', 'height', 'alt_text', 'ocr_text', 'source_page', 'role']);

        foreach ($rows as $row) {
            $byQuestion[(int) $row->question_id][] = [
                'url' => $row->stored_url,
                'sha256' => $row->asset_sha256,
                'width' => $row->width !== null ? (int) $row->width : null,
                'height' => $row->height !== null ? (int) $row->height : null,
                'caption' => $row->alt_text,
                'ocr_text' => $row->ocr_text,
                'page' => $row->source_page !== null ? (int) $row->source_page : null,
                'role' => $row->role,
            ];
        }

        return $byQuestion;
    }

    /**
     * Flatten a row plus its options and figures into the API shape.
     */
    private function shape($rows, array $options, array $figures): array
    {
        $data = [];

        foreach ($rows as $row) {
            $id = (int) $row->id;
            $envelope = $this->decodeEnvelope($row->answer_envelope ?? null);
            $rawType = strtolower(trim((string) ($row->lms_question_type ?? '')));
            $isMcq = in_array($rawType, ['mcq', 'multiple', 'multiple choice', 'multiple_choice'], true);
            // Sidecar (read off the source) -> envelope -> derived from the stem
            // -> the grading type. Without the derived tier the ~3k generated
            // questions all reported 'narrative' and showed catalog label "(none)".
            $code = $row->question_type_code
                ?? ($envelope['item_form'] ?? null)
                ?? ($row->derived_type_code ?? null)
                ?? ($isMcq ? 'mcq' : 'narrative');

            $data[] = [
                'id' => $id,
                'question' => (string) ($row->question_title ?? ''),
                'description' => $row->description,
                'marks' => (int) ($row->marks ?? 1),
                'status' => (int) ($row->status ?? 0),
                'category' => $row->category,

                'chapter_id' => $row->chapter_id !== null ? (int) $row->chapter_id : null,
                'chapter_name' => $row->chapter_name,
                'concept_id' => $row->concept_id !== null ? (int) $row->concept_id : null,
                'concept_name' => $row->concept_name ?? $row->concept_text,
                'standard_id' => $row->standard_id !== null ? (int) $row->standard_id : null,
                'standard_name' => $row->standard_name,
                'subject_id' => $row->subject_id !== null ? (int) $row->subject_id : null,
                'subject_name' => $row->subject_name,

                // Both vocabularies: the grading engine's collapsed label and
                // the real form, so the UI can show "Assertion-Reason" while
                // the runtime still knows it grades like an MCQ.
                'question_type' => $isMcq ? 'MCQ' : 'Narrative',
                'question_type_code' => $code,
                'exam_section' => $row->exam_section ?? ($envelope['exam_section'] ?? null),
                'item_number' => $row->item_number ?? ($envelope['item_number'] ?? null),

                'options' => $options[$id] ?? [],
                'correct_option' => $envelope['correct_option'] ?? null,
                'model_answer' => $this->readableModelAnswer($envelope, $row->answer_envelope ?? null),
                'assertion' => $envelope['assertion'] ?? null,
                'reason' => $envelope['reason'] ?? null,
                'sub_part_labels' => $envelope['sub_part_labels'] ?? [],

                'figures' => $figures[$id] ?? [],
                'figure_required' => (bool) ($row->figure_required ?? false),
                'figure_resolved' => (bool) ($row->figure_resolved ?? false),

                'bloom' => $row->bloom,
                'dok' => $row->dok !== null ? (int) $row->dok : null,
                'difficulty' => $row->difficulty,
                'concept_confidence' => isset($row->concept_confidence) && $row->concept_confidence !== null
                    ? (float) $row->concept_confidence
                    : null,
                'ai_model' => $row->ai_model ?? null,

                'publisher' => isset($row->publisher_name) && $row->publisher_name
                    ? [
                        'code' => $row->publisher_code,
                        'name' => $row->publisher_name,
                        'short_name' => $row->publisher_short,
                    ]
                    : null,
                'attribution' => $row->attribution ?? null,
                'licence' => $row->licence ?? null,
                'reproduction' => $row->reproduction ?? null,
                'validation_status' => $row->validation_status ?? null,
                // No sidecar row means nothing extracted it, so it came from
                // the generator.
                'source' => isset($row->extraction_id) && $row->extraction_id
                    ? 'extracted'
                    : 'ai_generated',
            ];
        }

        return $data;
    }

    private function decodeEnvelope($value): array
    {
        if (!is_string($value)) {
            return [];
        }

        $trimmed = trim($value);
        if ($trimmed === '' || !str_starts_with($trimmed, '{')) {
            return [];
        }

        $decoded = json_decode($trimmed, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Prose answer only. An MCQ envelope has no model_answer, and returning
     * the raw JSON there is what forces every caller to guard with an
     * isLikelyJson() check -- so return null instead.
     */
    private function readableModelAnswer(array $envelope, $raw): ?string
    {
        $model = $envelope['model_answer'] ?? null;
        if (is_string($model) && trim($model) !== '') {
            return $model;
        }

        if ($envelope !== []) {
            return null;
        }

        return is_string($raw) && trim($raw) !== '' ? $raw : null;
    }
}
