<?php

namespace App\Http\Controllers\lms\nextAPI;

use App\Http\Controllers\Controller;
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

        return response()->json($this->transformCurriculum($getCurriculumData));
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
