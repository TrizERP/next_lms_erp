<?php

namespace App\Http\Controllers\api\lms;

use App\Http\Controllers\Controller;
use App\Models\lms\contentModel;
use App\Models\lms\questionpaperModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Read-only lookups for the Lesson Plan document editor: the chapter's topics, the
 * generic mapping type/value tree, and the content-bank / question-bank search behind
 * the "activity" pickers (classroom / self-study / assessment).
 *
 * Mirrors the filter semantics of the old Blade screen's `create()` topic query and its
 * `ajax_contentMasterData()` / `ajax_questionPaperData()` AJAX endpoints
 * (lms_lessonplanController.php), but scoped by sub_institute_id - the old AJAX
 * endpoints never filtered by it even though both content_master and question_paper
 * carry the column, which is a latent cross-tenant leak this closes.
 */
class LessonPlanDocumentLookupApiController extends Controller
{
    /**
     * GET|POST /api/lesson-plan-doc/topics
     */
    public function topics(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'sub_institute_id' => 'required|integer',
            'chapter_id'       => 'required|integer',
        ]);

        if ($validator->fails()) {
            return $this->validationFailed($validator);
        }

        $filters = $validator->validated();

        $topics = DB::table('topic_master')
            ->select('id', 'name')
            ->where('sub_institute_id', $filters['sub_institute_id'])
            ->where('chapter_id', $filters['chapter_id'])
            ->orderBy('topic_sort_order')
            ->get();

        return response()->json([
            'status'  => true,
            'message' => 'Topics found',
            'data'    => $topics,
        ], 200);
    }

    /**
     * GET|POST /api/lesson-plan-doc/mapping-options
     */
    public function mappingOptions(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'chapter_id' => 'nullable|integer',
            'topic_id'   => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return $this->validationFailed($validator);
        }

        $filters = $validator->validated();
        $chapterId = $filters['chapter_id'] ?? null;
        $topicId = $filters['topic_id'] ?? null;

        $types = DB::table('lms_mapping_type')
            ->select('id', 'name')
            ->where('status', 1)
            ->where('parent_id', 0)
            ->where(function ($q) use ($chapterId) {
                $q->where('globally', 1)->orWhere('chapter_id', $chapterId);
            })
            ->where(function ($q) use ($topicId) {
                $q->where('topic_id', 0)->orWhere('topic_id', $topicId);
            })
            ->get();

        $values = [];
        if ($types->isNotEmpty()) {
            $allValues = DB::table('lms_mapping_type')
                ->select('id', 'name', 'parent_id')
                ->where('status', 1)
                ->whereIn('parent_id', $types->pluck('id'))
                ->get();

            foreach ($allValues as $value) {
                $values[$value->parent_id][] = ['id' => $value->id, 'name' => $value->name];
            }
        }

        return response()->json([
            'status'  => true,
            'message' => 'Mapping options found',
            'data'    => [
                'types'  => $types,
                'values' => $values,
            ],
        ], 200);
    }

    /**
     * GET|POST /api/lesson-plan-doc/content-search
     */
    public function contentSearch(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'sub_institute_id' => 'required|integer',
            'standard_id'      => 'nullable|integer',
            'chapter_id'       => 'nullable|integer',
            'subject_id'       => 'nullable|integer',
            'topic_id'         => 'nullable|integer',
            'q'                => 'nullable|string|max:150',
        ]);

        if ($validator->fails()) {
            return $this->validationFailed($validator);
        }

        $filters = $validator->validated();

        $content = contentModel::select('id', 'title')
            ->where('sub_institute_id', $filters['sub_institute_id'])
            ->when($filters['standard_id'] ?? null, fn ($q, $value) => $q->where('standard_id', $value))
            ->when($filters['chapter_id'] ?? null, fn ($q, $value) => $q->where('chapter_id', $value))
            ->when($filters['subject_id'] ?? null, fn ($q, $value) => $q->where('subject_id', $value))
            ->when($filters['topic_id'] ?? null, fn ($q, $value) => $q->where('topic_id', $value))
            ->when($filters['q'] ?? null, fn ($q, $value) => $q->where('title', 'like', '%' . $value . '%'))
            ->orderBy('title')
            ->limit(50)
            ->get();

        return response()->json([
            'status'  => true,
            'message' => 'Content found',
            'data'    => $content,
        ], 200);
    }

    /**
     * GET|POST /api/lesson-plan-doc/question-paper-search
     */
    public function questionPaperSearch(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'sub_institute_id' => 'required|integer',
            'standard_id'      => 'nullable|integer',
            'subject_id'       => 'nullable|integer',
            'q'                => 'nullable|string|max:150',
        ]);

        if ($validator->fails()) {
            return $this->validationFailed($validator);
        }

        $filters = $validator->validated();

        $questionPapers = questionpaperModel::select('id', 'paper_name as title')
            ->where('sub_institute_id', $filters['sub_institute_id'])
            ->when($filters['standard_id'] ?? null, fn ($q, $value) => $q->where('standard_id', $value))
            ->when($filters['subject_id'] ?? null, fn ($q, $value) => $q->where('subject_id', $value))
            ->when($filters['q'] ?? null, fn ($q, $value) => $q->where('paper_name', 'like', '%' . $value . '%'))
            ->orderBy('paper_name')
            ->limit(50)
            ->get();

        return response()->json([
            'status'  => true,
            'message' => 'Question papers found',
            'data'    => $questionPapers,
        ], 200);
    }

    private function validationFailed(\Illuminate\Contracts\Validation\Validator $validator): JsonResponse
    {
        return response()->json([
            'status'  => false,
            'message' => 'Validation failed',
            'errors'  => $validator->errors(),
        ], 422);
    }
}
