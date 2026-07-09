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
            return response()->json(['errors' => $validator->errors()->messages()], 422);
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
            $chapterId = $chapterData->chapter_id;

            if (!isset($formattedResponse[$chapterId])) {
                $formattedResponse[$chapterId] = [
                    'chapter_id' => (int)$chapterData->chapter_id,
                    'chapter_name' => $chapterData->chapter_name,
                    'concepts' => []
                ];
            }

            // Build semantic data - exclude null values
            $semanticData = [
                'semantic_id' => $chapterData->semantic_id,
                'learning_objective' => $chapterData->learning_objective,
                'total_concepts' => $chapterData->total_concepts,
                'full_intelegance_json' => $chapterData->full_intelegance_json,
                'knowledge' => json_decode($chapterData->knowledge,true),
                'ability' => json_decode($chapterData->ability,true),
                'skill' => json_decode($chapterData->skill,true),
                'competency' => json_decode($chapterData->competency,true),
                'blooms_level' => json_decode($chapterData->blooms_level,true),
                'dok' => json_decode($chapterData->dok,true),
                'prerequisites' => json_decode($chapterData->prerequisites,true),
                'misconceptions' => json_decode($chapterData->misconceptions,true),
                'real_world_applications' => json_decode($chapterData->real_world_applications,true),
                'pedagogy' => json_decode($chapterData->pedagogy,true),
                'learning_objectives' => json_decode($chapterData->learning_objectives,true),
                'learning_outcomes' => json_decode($chapterData->learning_outcomes,true),
                'assessment_blueprint' => json_decode($chapterData->assessment_blueprint,true)
            ];

            // Remove null values
            $semanticData = array_filter($semanticData, function ($value) {
                return $value !== null;
            });

            $formattedResponse[$chapterId]['concepts'][] = [
                'concept_id' => (int)$chapterData->concept_id,
                'concept_name' => $chapterData->concept_name,
                'concept_description' => $chapterData->concept_description,
                'semantic' => $semanticData
            ];
        }

        // Convert to array values to reset numeric keys
        $finalResponse = array_values($formattedResponse);

        return response()->json($finalResponse);
    }
}
