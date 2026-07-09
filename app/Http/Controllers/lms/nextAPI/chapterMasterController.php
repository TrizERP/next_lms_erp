<?php

namespace App\Http\Controllers\lms\nextAPI;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class chapterMasterController extends Controller
{
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
            return response()->json([
                'status'  => false,
                'message' => 'Validation failed.',
                'errors'  => $validator->errors()->messages(),
                'data'    => [],
            ], 422);
        }

        $getChapterData = DB::table('chapter_master as a')
            ->select(
                'a.id as chapter_id',
                'a.subject_id',
                'a.standard_id',
                'a.chapter_name',
                'b.id as concept_id',
                'b.name as concept_name',
                'b.description as concept_description',
                'c.id as semantic_id',
                'c.learning_objective',
                'c.total_concepts',
                'c.full_intelegance_json',
                'c.knowledge',
                'c.ability',
                'c.skill',
                'c.competency',
                'c.blooms_level',
                'c.dok',
                'c.prerequisites',
                'c.misconceptions',
                'c.real_world_applications',
                'c.pedagogy',
                'c.learning_objectives',
                'c.learning_outcomes',
                'c.assessment_blueprint'
                // Add other semantic fields as needed
            )
            ->join('lms_concept as b', function ($query) use ($sub_institute_id) {
                $query->on('b.chapter_id', '=', 'a.id')
                    ->on('a.subject_id', '=', 'b.subject_id')
                    ->where('a.sub_institute_id', '=', $sub_institute_id);
            })
            ->join('semantic_intelligence as c', function ($query) {
                $query->on('c.chapter_id', '=', 'b.chapter_id')
                    ->on('a.subject_id', '=', 'c.subject_id');
            })
            ->where([
                'a.sub_institute_id' => $sub_institute_id,
                'a.standard_id' => $standard_id,
                'a.subject_id' => $subject_id
            ])
            ->groupBy('b.id')
            ->get();

        // Format the response
        $formattedResponse = [];

        foreach ($getChapterData as $chapterData) {
            $chapterId = (int) $chapterData->chapter_id;

            if (!isset($formattedResponse[$chapterId])) {
                $formattedResponse[$chapterId] = [
                    'chapter_id'   => (int) $chapterData->chapter_id,
                    'subject_id'   => (int) $chapterData->subject_id,
                    'standard_id'  => (int) $chapterData->standard_id,
                    'chapter_name' => $chapterData->chapter_name,
                    'concepts'     => [],
                ];
            }

            // Build semantic data - decode JSON columns, drop null values.
            $semanticData = [
                'semantic_id'             => $chapterData->semantic_id !== null ? (int) $chapterData->semantic_id : null,
                'learning_objective'      => $chapterData->learning_objective,
                'total_concepts'          => $chapterData->total_concepts !== null ? (int) $chapterData->total_concepts : null,
                'full_intelegance_json'   => $this->decodeJson($chapterData->full_intelegance_json),
                'knowledge'               => $this->decodeJson($chapterData->knowledge),
                'ability'                 => $this->decodeJson($chapterData->ability),
                'skill'                   => $this->decodeJson($chapterData->skill),
                'competency'              => $this->decodeJson($chapterData->competency),
                'blooms_level'            => $this->decodeJson($chapterData->blooms_level),
                'dok'                     => $this->decodeJson($chapterData->dok),
                'prerequisites'           => $this->decodeJson($chapterData->prerequisites),
                'misconceptions'          => $this->decodeJson($chapterData->misconceptions),
                'real_world_applications' => $this->decodeJson($chapterData->real_world_applications),
                'pedagogy'                => $this->decodeJson($chapterData->pedagogy),
                'learning_objectives'     => $this->decodeJson($chapterData->learning_objectives),
                'learning_outcomes'       => $this->decodeJson($chapterData->learning_outcomes),
                'assessment_blueprint'    => $this->decodeJson($chapterData->assessment_blueprint),
            ];

            // Remove null values.
            $semanticData = array_filter($semanticData, function ($value) {
                return $value !== null;
            });

            $formattedResponse[$chapterId]['concepts'][] = [
                'concept_id'          => (int) $chapterData->concept_id,
                'concept_name'        => $chapterData->concept_name,
                'concept_description' => $chapterData->concept_description,
                'semantic'            => $semanticData ? (object) $semanticData : null,
            ];
        }

        // Convert to array values to reset numeric keys.
        $finalResponse = array_values($formattedResponse);

        return response()->json([
            'status'  => true,
            'message' => count($finalResponse) ? 'Chapter data fetched successfully.' : 'No chapter data found.',
            'data'    => $finalResponse,
        ], 200);
    }

    /**
     * Safely decode a JSON string column into an array.
     * Returns null when the value is empty or not valid JSON.
     */
    private function decodeJson($value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }
}
