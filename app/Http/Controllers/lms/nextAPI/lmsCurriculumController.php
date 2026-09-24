<?php

namespace App\Http\Controllers\lms\nextAPI;

use App\Http\Controllers\Controller;
use App\Services\Curriculum\CurriculumAssessmentParser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class lmsCurriculumController extends Controller
{
    //
    public function index(Request $request)
    {
        $subject_id = $request->subject_id;
        $standard_id = $request->standard_id;
        $sub_institute_id = $request->sub_institute_id;
        $syear = $request->syear;

        $validator = Validator::make($request->all(), [
            'sub_institute_id' => 'required',
            'standard_id' => 'required',
            'subject_id' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()->messages()], 422);
        }

        $getCurriculumData = DB::table('lms_curriculum as a')
            ->select('a.*', 'b.*', 'c.*')
            ->join('lms_units as b', 'b.curriculum_id', '=', 'a.id')
            ->join('lms_learning_outcomes as c', 'c.curriculum_id', '=', 'a.id')
            ->where('a.sub_institute_id', $sub_institute_id)
            ->where('a.standard_id', $standard_id)
            ->where('a.subject_id', $subject_id)
            ->orderBy('a.id')
            ->get();

        $result = $this->transformCurriculum($getCurriculumData);

        return response()->json($this->enrich($result, $sub_institute_id, $standard_id, $subject_id));
    }

    /**
     * Adds what the flat three-way join cannot carry: the real chapters under
     * each unit, and the marks structure behind internal_marks.
     **
     * Done as its own pass rather than by widening the join above. That join
     * multiplies units by learning outcomes, so every chapter added to it would
     * be repeated once per outcome - and `select('a.*','b.*','c.*')` already
     * silently loses `lms_curriculum.total_marks` to `lms_units.total_marks`,
     * which is why the curriculum's own total is re-read here by name.
     */
    private function enrich(array $result, $subInstituteId, $standardId, $subjectId): array
    {
        $curriculum = DB::table('lms_curriculum')
            ->where('sub_institute_id', $subInstituteId)
            ->where('standard_id', $standardId)
            ->where('subject_id', $subjectId)
            ->orderBy('id')
            ->first(['id', 'extraction_id', 'total_marks', 'internal_marks', 'chapter_periods']);

        if ($curriculum === null || $result === []) {
            return $result;
        }

        $markdown = $curriculum->extraction_id
            ? DB::table('document_extractions')->where('id', $curriculum->extraction_id)->value('md_content')
            : null;

        $assessment = (new CurriculumAssessmentParser())->parse(
            $markdown,
            $curriculum->total_marks === null ? null : (int) $curriculum->total_marks,
            $curriculum->internal_marks === null ? null : (int) $curriculum->internal_marks
        );

        // Planned periods per chapter, as the syllabus states them. Keyed by
        // chapter name, and the names it uses are a mix of the syllabus's own
        // wording and the extracted chapter titles ("Cell" beside "Exploring
        // Mixtures and their Separation"), so both are looked up.
        $periodsByName = [];
        foreach (json_decode((string) $curriculum->chapter_periods, true) ?: [] as $entry) {
            $name = is_array($entry) ? trim((string) ($entry['chapter_name'] ?? '')) : '';
            if ($name !== '' && isset($entry['no_of_periods'])) {
                $periodsByName[mb_strtolower($name)] = (int) $entry['no_of_periods'];
            }
        }

        $units = DB::table('lms_units')
            ->where('curriculum_id', $curriculum->id)
            ->get(['id', 'unit_number'])
            ->keyBy('unit_number');

        $chapterRows = DB::table('chapter_master')
            ->whereIn('unit_id', $units->pluck('id')->all() ?: [0])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'unit_id', 'chapter_name']);

        $chaptersByUnit = $chapterRows->groupBy('unit_id');

        // topic_master is the missing level between a chapter and its concepts.
        // It is read with the chapter rows already scoped by the curriculum,
        // preserving its author-defined sort order.
        $topicsByChapter = DB::table('topic_master')
            ->whereIn('chapter_id', $chapterRows->pluck('id')->all() ?: [0])
            ->orderBy('chapter_id')
            ->orderBy('topic_sort_order')
            ->orderBy('id')
            ->get(['id', 'chapter_id', 'name', 'description'])
            ->groupBy('chapter_id');

        // lms_concept.topic_id is the authoritative concept -> topic_master
        // link. Concepts are still fetched in one read; grouping happens below
        // after the chapter is known.
        $conceptRowsByChapter = DB::table('lms_concept')
            ->whereIn('chapter_id', $chapterRows->pluck('id')->all() ?: [0])
            ->orderBy('chapter_id')
            ->orderBy('id')
            ->get(['id', 'chapter_id', 'topic_id', 'name'])
            ->groupBy('chapter_id');

        // The real concept -> learning-outcome/competency mapping:
        // lms_concept_outcome keys on concept_id (an actual FK into
        // lms_concept, unlike lms_concept_intelligence_index's name matching)
        // and outcome_id into lms_learning_outcomes, which now carries three
        // tiers - goal, competency, learning_outcome - chained by parent_id.
        // competency_code/goal_code are denormalised onto every row, LO rows
        // included, so LOs group under their competency even on the ~3% of
        // concepts where the competency row itself isn't mapped to this
        // concept.
        $conceptOutcomesByConcept = DB::table('lms_concept_outcome as co')
            ->join('lms_learning_outcomes as lo', 'lo.id', '=', 'co.outcome_id')
            ->whereIn('co.chapter_id', $chapterRows->pluck('id')->all() ?: [0])
            ->orderBy('co.goal_code')
            ->orderBy('co.competency_code')
            ->orderBy('co.outcome_type')
            ->orderBy('co.outcome_code')
            ->get([
                'co.concept_id', 'co.outcome_type', 'co.outcome_code',
                'co.competency_code', 'co.goal_code', 'co.match_source', 'lo.description',
            ])
            ->groupBy('concept_id');

        // The chapter's own lms_learning_outcomes rows are the source of truth
        // for this tab. Return only their `code` values, never descriptions or
        // competency text inferred from an extraction document.
        $competencyCodesByChapter = DB::table('lms_learning_outcomes')
            ->whereIn('chapter_id', $chapterRows->pluck('id')->all() ?: [0])
            ->whereNotNull('parent_id')
            ->whereNotNull('code')
            ->where('code', '<>', '')
            ->orderBy('chapter_id')
            ->orderBy('code')
            ->get(['chapter_id', 'code'])
            ->groupBy('chapter_id')
            ->map(fn ($rows) => $rows
                ->pluck('code')
                ->map(fn ($code) => trim((string) $code))
                ->filter()
                ->unique()
                ->values()
                ->all());

        foreach ($result as &$entry) {
            $entry['curriculum_data']['total_marks'] = $curriculum->total_marks;
            $entry['curriculum_data']['assessment'] = $assessment;

            foreach ($entry['unit_data'] as &$unit) {
                $unitId = $units->get($unit['unit_number'] ?? null)->id ?? null;
                $declared = $this->declaredChapters($unit['unit_chapters'] ?? null);

                $extracted = ($unitId === null ? collect() : ($chaptersByUnit->get($unitId) ?? collect()))->values();

                // The chapter level of the tree is the unit's own chapter list
                // from lms_units. Those names are what the syllabus calls them
                // and what this screen has always shown - "Cell", not "Cell:
                // The Building Block of Life".
                //
                // lms_units holds nothing else: no concepts, no periods, no ids.
                // chapter_master.key_concepts is the only key-concepts column in
                // the schema and chapter_master.unit_id the only link from a
                // concept to a unit, so the concepts beneath each chapter have
                // to be carried across from there.
                //
                // The two lists are lined up by position, and ONLY when the unit
                // declares exactly as many chapters as were extracted from it.
                // That guard is the whole safety of it: where the counts differ
                // the order means nothing, and 50 of this estate's 60 units
                // differ. Under the guard it is verifiable - Cell, Tissues,
                // Reproduction, Diversity line up with their four extracted
                // titles in order.
                $canPair = count($declared) > 0 && count($declared) === $extracted->count();

                if (count($declared) > 0) {
                    $chapters = [];

                    foreach ($declared as $index => $declaredName) {
                        $match = $this->matchDeclaredChapter($declaredName, $extracted, $canPair, $index);
                        $conceptRows = $match ? ($conceptRowsByChapter->get($match->id) ?? collect()) : collect();
                        $concepts = $conceptRows
                            ->map(fn ($concept) => [
                                'name' => trim((string) $concept->name),
                                'competencies' => $this->conceptCompetencies($concept->id, $conceptOutcomesByConcept),
                            ])
                            ->filter(fn ($concept) => $concept['name'] !== '')
                            ->values()
                            ->all();
                        $topics = $match
                            ? $this->chapterTopics(
                                $topicsByChapter->get($match->id) ?? collect(),
                                $conceptRows,
                                $conceptOutcomesByConcept
                            )
                            : [];

                        $periods = $periodsByName[mb_strtolower(trim((string) $declaredName))] ?? null;
                        if ($periods === null && $match) {
                            $periods = $periodsByName[mb_strtolower(trim((string) $match->chapter_name))] ?? null;
                        }

                        $chapters[] = [
                            'chapter_id'     => $match ? (int) $match->id : null,
                            'chapter_name'   => $declaredName,
                            // The extracted title for the same chapter, so the
                            // longer name the rest of the LMS uses stays visible.
                            'extracted_name' => $match->chapter_name ?? null,
                            'topics'         => $topics,
                            'topic_count'    => count($topics),
                            'competency_codes' => $match
                                ? ($competencyCodesByChapter->get($match->id) ?? [])
                                : [],
                            'concepts'       => $concepts,
                            'concept_count'  => count($concepts),
                            'periods'        => $periods,
                        ];
                    }
                } else {
                    // A unit that declares no chapters at all but has extracted
                    // ones - Hindi unit 3 holds eleven. Falling back to them
                    // keeps real content on screen instead of "No chapters".
                    $chapters = $extracted
                        ->map(function ($chapter) use ($periodsByName, $topicsByChapter, $conceptRowsByChapter, $competencyCodesByChapter, $conceptOutcomesByConcept) {
                            $conceptRows = $conceptRowsByChapter->get($chapter->id) ?? collect();
                            $concepts = $conceptRows
                                ->map(fn ($concept) => [
                                    'name' => trim((string) $concept->name),
                                    'competencies' => $this->conceptCompetencies($concept->id, $conceptOutcomesByConcept),
                                ])
                                ->filter(fn ($concept) => $concept['name'] !== '')
                                ->values()
                                ->all();
                            $topics = $this->chapterTopics(
                                $topicsByChapter->get($chapter->id) ?? collect(),
                                $conceptRows,
                                $conceptOutcomesByConcept
                            );

                            return [
                                'chapter_id'     => (int) $chapter->id,
                                'chapter_name'   => $chapter->chapter_name,
                                'extracted_name' => null,
                                'topics'         => $topics,
                                'topic_count'    => count($topics),
                                'competency_codes' => $competencyCodesByChapter->get($chapter->id) ?? [],
                                'concepts'       => $concepts,
                                'concept_count'  => count($concepts),
                                'periods'        => $periodsByName[mb_strtolower(trim((string) $chapter->chapter_name))] ?? null,
                            ];
                        })
                        ->values()
                        ->all();
                }

                $unit['chapters'] = $chapters;
            }
            unset($unit);
        }
        unset($entry);

        return $result;
    }

    /** `unit_chapters` is a JSON array of names, and is sometimes already one. */
    private function declaredChapters($value): array
    {
        if (is_array($value)) {
            return array_values(array_filter($value, 'is_string'));
        }

        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? array_values(array_filter($decoded, 'is_string')) : [];
    }

    /**
     * Resolve one declared chapter name to its chapter_master row.
     *
     * Positional pairing ($canPair) is the primary and safest method - it is
     * only trusted when the unit declares exactly as many chapters as were
     * extracted. Where the counts differ (48% of units), position means
     * nothing, but roughly a fifth of those declared chapters can still be
     * resolved safely by name: either an exact match, or a name that is a
     * substring of the other with exactly one such candidate in the unit.
     * Never resolved by a fuzzy/best-effort match - an ambiguous or absent
     * name match leaves chapter_id null rather than guessing, same as before.
     */
    private function matchDeclaredChapter(string $declaredName, $extracted, bool $canPair, int $index)
    {
        if ($canPair) {
            return $extracted->get($index);
        }

        $normalized = mb_strtolower(trim($declaredName));
        if ($normalized === '') {
            return null;
        }

        $exact = $extracted->first(
            fn ($chapter) => mb_strtolower(trim((string) $chapter->chapter_name)) === $normalized
        );
        if ($exact !== null) {
            return $exact;
        }

        $candidates = $extracted->filter(function ($chapter) use ($normalized) {
            $chapterName = mb_strtolower(trim((string) $chapter->chapter_name));

            return $chapterName !== '' && (
                str_contains($chapterName, $normalized) || str_contains($normalized, $chapterName)
            );
        });

        return $candidates->count() === 1 ? $candidates->first() : null;
    }

    /** Build topic_master -> lms_concept rows without ever deriving a topic by name. */
    private function chapterTopics($topics, $conceptRows, $conceptOutcomesByConcept): array
    {
        return collect($topics)
            ->map(function ($topic) use ($conceptRows, $conceptOutcomesByConcept) {
                $concepts = collect($conceptRows)
                    ->where('topic_id', $topic->id)
                    ->map(fn ($concept) => [
                        'name' => trim((string) $concept->name),
                        'competencies' => $this->conceptCompetencies($concept->id, $conceptOutcomesByConcept),
                    ])
                    ->filter(fn ($concept) => $concept['name'] !== '')
                    ->values()
                    ->all();

                return [
                    'topic_id'    => (int) $topic->id,
                    'name'        => trim((string) $topic->name),
                    'description' => trim((string) ($topic->description ?? '')) ?: null,
                    'concepts'    => $concepts,
                ];
            })
            ->filter(fn ($topic) => $topic['name'] !== '')
            ->values()
            ->all();
    }

    /**
     * A concept's own competencies, and the learning outcomes mapped beneath
     * each one - both read from lms_concept_outcome, the real concept_id ->
     * outcome_id mapping (never inferred by name).
     *
     * lms_concept_outcome was built in two passes, recorded in match_source:
     * 'chapter_scope' assigns every competency of a chapter to every concept
     * in it (score 0.6) - by far the largest source (4,047 rows) - while
     * 'llm' (score 0.9) and 'token_overlap' match a competency to the specific
     * concepts it actually covers. Reading chapter_scope rows without
     * preference is why every concept in a chapter was rendering the same
     * competency set: it *is* the same set, on purpose, at that grain. Where a
     * concept has any llm/token_overlap competency match, only that
     * concept-specific set is used; chapter_scope is a fallback for concepts
     * with no specific match at all (~1,967 of 6,892).
     *
     * Learning-outcome rows are not filtered the same way: 98% of them carry
     * match_source 'inherited', because once a competency is selected for a
     * concept its learning outcomes come with it structurally (they describe
     * that competency, not the concept) - filtering LOs by source would drop
     * nearly all of them for no reason.
     */
    private function conceptCompetencies($conceptId, $conceptOutcomesByConcept): array
    {
        $rows = $conceptOutcomesByConcept->get($conceptId);

        if ($rows === null) {
            return [];
        }

        $competencyRows = $rows->where('outcome_type', 'competency');
        $specificCompetencyRows = $competencyRows->whereIn('match_source', ['llm', 'token_overlap']);
        $competencyRows = $specificCompetencyRows->isNotEmpty() ? $specificCompetencyRows : $competencyRows;

        $learningOutcomeRows = $rows->where('outcome_type', 'learning_outcome');

        return $competencyRows
            ->pluck('competency_code')
            ->unique()
            ->map(function ($competencyCode) use ($competencyRows, $learningOutcomeRows, $rows) {
                $competencyRow = $competencyRows->firstWhere('competency_code', $competencyCode);

                $learningOutcomes = $learningOutcomeRows
                    ->where('competency_code', $competencyCode)
                    ->unique('outcome_code')
                    ->map(fn ($row) => [
                        'code'  => $row->outcome_code,
                        'label' => trim((string) $row->description),
                    ])
                    ->filter(fn ($outcome) => $outcome['label'] !== '')
                    ->values()
                    ->all();

                return [
                    'code'              => $competencyCode,
                    'goal_code'         => optional($rows->firstWhere('competency_code', $competencyCode))->goal_code,
                    'label'             => $competencyRow ? trim((string) $competencyRow->description) : null,
                    'learning_outcomes' => $learningOutcomes,
                ];
            })
            ->filter(fn ($competency) => $competency['label'] !== null || $competency['learning_outcomes'] !== [])
            ->values()
            ->all();
    }

    /**
     * Competency codes are authored only in document_extractions.md_content.
     * Match a chapter's own markdown section first, then return its C-* / C=*
     * labels without descriptions or unrelated curriculum-wide competency rows.
     */
    private function chapterCompetencyCodes(?string $markdown, string ...$chapterNames): array
    {
        if ($markdown === null || trim($markdown) === '') {
            return [];
        }

        $names = collect($chapterNames)
            ->map(fn ($name) => mb_strtolower(trim(html_entity_decode(strip_tags($name)))))
            ->filter(fn ($name) => $name !== '')
            ->unique()
            ->values();

        if ($names->isEmpty()) {
            return [];
        }

        // Markdown/HTML headings and plain "Chapter 3:" headings all delimit
        // extracted chapter sections. The latter is common in OCR output.
        $sections = preg_split('/(?=^\s*(?:#{1,6}\s+|(?:chapter|unit)\s+\d+[\s:.-])|<h[1-6]\\b)/mi', $markdown) ?: [];
        foreach ($sections as $section) {
            $plain = mb_strtolower(html_entity_decode(strip_tags($section)));
            if (!$names->contains(fn ($name) => str_contains($plain, $name))) {
                continue;
            }

            if (preg_match_all('/\bC\s*[-=]\s*\d+(?:\.\d+)+\b/ui', $plain, $matches)) {
                return collect($matches[0])
                    ->map(fn ($code) => preg_replace('/\s+/', '', $code))
                    ->unique()
                    ->values()
                    ->all();
            }
        }

        return [];
    }

    /**
     * Transform the flat (cross-joined) curriculum rows into a nested structure:
     * [
     *   {
     *     curriculum_data: {...},
     *     unit_data: [ {unit}, ... ],
     *     outcomes: [ {parent (parent_id null), children: [ {child}, ... ]}, ... ]
     *   }
     * ]
     */
    private function transformCurriculum($rows)
    {
        // Fields that belong to the curriculum (constant per curriculum_id).
        $curriculumFields = [
            'curriculum_id', 'extraction_id', 'sub_institute_id', 'grade_id',
            'standard_id', 'subject_id', 'board_id', 'curriculum_name',
            'curriculum_alignment', 'holistic_curriculum', 'model_integration',
            'syear', 'board', 'framework', 'internal_marks', 'status',
            'created_at', 'updated_at',
        ];

        // Fields that describe a unit (vary by unit_number).
        $unitFields = [
            'unit_number', 'name', 'unit_chapters', 'total_marks',
            'planned_periods', 'chapter_id',
        ];

        // Fields that describe a learning outcome / period (vary by code/id).
        $periodFields = [
            'id', 'code', 'type', 'parent_id', 'description',
            'objective', 'chapter', 'outcome', 'assessment_tool',
        ];

        $curricula = [];

        foreach ($rows as $row) {
            $row = (array) $row;
            $curriculumId = $row['curriculum_id'] ?? null;

            if (!isset($curricula[$curriculumId])) {
                $curricula[$curriculumId] = [
                    'curriculum_data' => $this->pick($row, $curriculumFields),
                    'unit_data'       => [],
                    'outcomes'        => [],
                    '_units'          => [], // temp index by unit_number
                    '_periods'        => [], // temp index by period id
                ];
            }

            // Collect unique units (keyed by unit_number).
            $unitKey = $row['unit_number'] ?? null;
            if ($unitKey !== null && !isset($curricula[$curriculumId]['_units'][$unitKey])) {
                $curricula[$curriculumId]['_units'][$unitKey] = $this->pick($row, $unitFields);
            }

            // Collect unique periods (keyed by learning outcome id).
            $periodKey = $row['id'] ?? null;
            if ($periodKey !== null && !isset($curricula[$curriculumId]['_periods'][$periodKey])) {
                $period = $this->pick($row, $periodFields);
                $period['children'] = [];
                $curricula[$curriculumId]['_periods'][$periodKey] = $period;
            }
        }

        // Build the final nested structure.
        $result = [];
        foreach ($curricula as $curriculum) {
            $curriculum['unit_data'] = array_values($curriculum['_units']);

            // Nest periods: parents (parent_id null) hold their children.
            $periods = $curriculum['_periods'];
            $tree = [];
            foreach ($periods as $id => $period) {
                if (empty($period['parent_id'])) {
                    $tree[$id] = $period;
                }
            }
            foreach ($periods as $period) {
                $parentId = $period['parent_id'] ?? null;
                if (!empty($parentId) && isset($tree[$parentId])) {
                    $tree[$parentId]['children'][] = $period;
                }
            }
            $curriculum['outcomes'] = array_values($tree);

            unset($curriculum['_units'], $curriculum['_periods']);
            $result[] = $curriculum;
        }

        return array_values($result);
    }

    /**
     * Return only the given keys from an associative array.
     */
    private function pick(array $row, array $keys)
    {
        $picked = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $row)) {
                $picked[$key] = $row[$key];
            }
        }
        return $picked;
    }
}
