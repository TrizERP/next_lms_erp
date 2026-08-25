<?php

namespace App\Http\Controllers\lms\nextAPI;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache; 

class chapterMasterController extends Controller
{
    public function index(Request $request)
    {
        // 1. Strict Validation to ensure we receive numbers
        $validator = Validator::make($request->all(), [
            'sub_institute_id' => 'required|integer',
            'standard_id'      => 'required|integer',
            'subject_id'       => 'required|integer'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false, 
                'message' => 'Validation failed.', 
                'errors'  => $validator->errors()->messages()
            ], 422);
        }

        // 2. Cast to Integers to force MySQL to use Indexes efficiently
        $subInstituteId = (int) $request->sub_institute_id;
        $standardId     = (int) $request->standard_id;
        $subjectId      = (int) $request->subject_id;
        
        $perPage = (int) $request->input('per_page', 20);
        $perPage = $perPage > 0 ? min($perPage, 100) : 20;
        $page    = (int) $request->input('page', 1);

        // 3. Unique Cache Key (Data is cached for 1 Hour)
        //    v2: response shape changed — full_intelegance_json removed from the list
        //    payload (now fetched lazily). Bumping the version invalidates any v1
        //    cache entries still holding the old heavy shape.
        //    v3: chapters now carry their topic_master rows, and each concept
        //    carries the topic it belongs to, so the UI can render
        //    chapter -> topic -> concept.
        $cacheKey = "chapters_v3_{$subInstituteId}_{$standardId}_{$subjectId}_page_{$page}_limit_{$perPage}";
        
        return Cache::remember($cacheKey, 3600, function () use ($subInstituteId, $standardId, $subjectId, $perPage) {
            
            // 4. Paginate Chapters (Database Level)
            $paginatedChapters = DB::table('chapter_master')
                ->select('id as chapter_id', 'subject_id', 'standard_id', 'chapter_name')
                ->where('sub_institute_id', $subInstituteId)
                ->where('standard_id', $standardId)
                ->where('subject_id', $subjectId)
                ->paginate($perPage);

            $chapterIds = collect($paginatedChapters->items())->pluck('chapter_id')->toArray();
            $formattedResponse = [];

            // 5. Setup Structure with Semantic object at the root of Chapter
            foreach ($paginatedChapters->items() as $chapter) {
                $formattedResponse[$chapter->chapter_id] = [
                    'chapter_id'   => (int) $chapter->chapter_id,
                    'subject_id'   => (int) $chapter->subject_id,
                    'standard_id'  => (int) $chapter->standard_id,
                    'chapter_name' => $chapter->chapter_name,
                    'semantic'     => null, 
                    'topics'       => [],
                    'concepts'     => [],
                ];
            }

            if (!empty($chapterIds)) {
                // 6. Fetch related Concepts
                $concepts = DB::table('lms_concept')
                    ->select('id as concept_id', 'chapter_id', 'topic_id', 'name as concept_name', 'description as concept_description')
                    ->whereIn('chapter_id', $chapterIds)
                    ->get();

                // 6b. Fetch the topics these chapters are split into. topic_master is
                //     the curriculum's own chapter -> topic breakdown; lms_concept.topic_id
                //     points back at it, which is what lets the UI nest concepts under
                //     their topic instead of listing them flat under the chapter.
                $topics = DB::table('topic_master')
                    ->select(
                        'id as topic_id',
                        'chapter_id',
                        'name as topic_name',
                        'description as topic_description',
                        'topic_sort_order',
                        'topic_show_hide'
                    )
                    ->whereIn('chapter_id', $chapterIds)
                    ->orderBy('topic_sort_order')
                    ->orderBy('id')
                    ->get();

                // 7. Fetch lightweight Semantic data.
                //    NOTE: full_intelegance_json is intentionally NOT selected here.
                //    That column is the heavy blob (hundreds of KB per chapter) and is
                //    only needed when a user opens a concept's intelligence drawer.
                //    It is fetched lazily, per chapter, via
                //    GET /api/semantic-intelligence/{chapter_id}/result.
                $semantics = DB::table('semantic_intelligence')
                    ->select('id as semantic_id', 'chapter_id', 'learning_objective', 'total_concepts')
                    ->whereIn('chapter_id', $chapterIds)
                    ->get();

                // 8. Attach lightweight Semantic Data (summary only, no intelligence blob)
                foreach ($semantics as $semantic) {
                    $formattedResponse[$semantic->chapter_id]['semantic'] = (object) [
                        'semantic_id'        => (int) $semantic->semantic_id,
                        'learning_objective' => $semantic->learning_objective,
                        'total_concepts'     => $semantic->total_concepts !== null ? (int) $semantic->total_concepts : null,
                    ];
                }

                // 9. Attach Topics
                foreach ($topics as $topic) {
                    $formattedResponse[$topic->chapter_id]['topics'][] = [
                        'topic_id'          => (int) $topic->topic_id,
                        'topic_name'        => $topic->topic_name,
                        'topic_description' => $topic->topic_description,
                        'topic_sort_order'  => $topic->topic_sort_order !== null ? (int) $topic->topic_sort_order : null,
                        'topic_show_hide'   => $topic->topic_show_hide !== null ? (int) $topic->topic_show_hide : null,
                    ];
                }

                // 10. Attach Concepts, each tagged with the topic it sits under.
                //     topic_id is null for concepts extracted before the topic split,
                //     and the UI groups those separately rather than dropping them.
                foreach ($concepts as $concept) {
                    $formattedResponse[$concept->chapter_id]['concepts'][] = [
                        'concept_id'          => (int) $concept->concept_id,
                        'topic_id'            => $concept->topic_id !== null ? (int) $concept->topic_id : null,
                        'concept_name'        => $concept->concept_name,
                        'concept_description' => $concept->concept_description,
                    ];
                }
            }

            return response()->json([
                'status'     => true,
                'message'    => count($formattedResponse) ? 'Chapter data fetched successfully.' : 'No chapter data found.',
                'data'       => array_values($formattedResponse),
                'pagination' => [
                    'current_page' => $paginatedChapters->currentPage(),
                    'per_page'     => $paginatedChapters->perPage(),
                    'total'        => $paginatedChapters->total(),
                    'last_page'    => $paginatedChapters->lastPage(),
                    'from'         => $paginatedChapters->firstItem(),
                    'to'           => $paginatedChapters->lastItem(),
                ],
            ], 200);
        });
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