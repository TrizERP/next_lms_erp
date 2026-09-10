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
     *
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

        // The concepts themselves. lms_concept is the concept registry - around
        // fifty rows per chapter here, against the dozen or so summary entries
        // in chapter_master.key_concepts - and its count is already what this
        // screen's header totals.
        //
        // Joined on chapter_id alone, deliberately: lms_concept is 100% tenant 1
        // and tenant 341, so a sub_institute_id filter here returns nothing for
        // most schools. The chapters were already scoped by curriculum, so the
        // tenant is settled before this query runs.
        //
        // Names only. They are a level of the tree; the descriptions beside them
        // are far larger and nothing on this screen reads one.
        $conceptsByChapter = DB::table('lms_concept')
            ->whereIn('chapter_id', $chapterRows->pluck('id')->all() ?: [0])
            ->orderBy('chapter_id')
            ->orderBy('id')
            ->get(['chapter_id', 'name'])
            ->groupBy('chapter_id')
            ->map(fn ($rows) => $rows
                ->pluck('name')
                ->map(fn ($name) => trim((string) $name))
                ->filter()
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
                        $match = $canPair ? $extracted->get($index) : null;
                        $concepts = $match ? ($conceptsByChapter->get($match->id) ?? []) : [];

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
                        ->map(function ($chapter) use ($periodsByName, $conceptsByChapter) {
                            $concepts = $conceptsByChapter->get($chapter->id) ?? [];

                            return [
                                'chapter_id'     => (int) $chapter->id,
                                'chapter_name'   => $chapter->chapter_name,
                                'extracted_name' => null,
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
