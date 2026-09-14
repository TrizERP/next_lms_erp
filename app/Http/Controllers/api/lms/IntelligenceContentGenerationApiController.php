<?php

namespace App\Http\Controllers\api\lms;

use App\Http\Controllers\Controller;
use App\Models\lms\chapterModel;
use App\Services\ContentGenerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Authenticated chapter-content generation.
 *
 * The sibling of IntelligenceQuestionGenerationApiController, and the
 * authenticated successor to lms/gamma-content-master. Same service, same
 * "the caller supplies the finished prompt" contract; the difference is that
 * the tenant and author are read from the verified session rather than the
 * request body, so a caller cannot write content into another school
 * attributed to another user.
 *
 * The legacy route still exists because the content drawer sends no bearer
 * token; re-gating it would break generation for every school. Point the
 * drawer here once it authenticates, then retire that route.
 */
class IntelligenceContentGenerationApiController extends Controller
{
    protected ContentGenerationService $service;

    public function __construct(ContentGenerationService $service)
    {
        $this->service = $service;
    }

    public function generate(Request $request): JsonResponse
    {
        // A long-form deck at effort=high runs for minutes, well past the
        // default 60s.
        set_time_limit((int) config('claude.timeout_seconds', 600) + 120);

        $validator = Validator::make($request->all(), [
            // Addressed by id, not by name. The legacy route looks the chapter
            // up by chapter_name, which is neither unique by contract nor
            // tenant-scoped.
            'chapter_id' => 'required|integer|min:1',
            'prompt' => 'required|string|max:400000',
            'content_type' => 'required|string|max:250',
            'concept_id' => 'nullable|integer',
            // sub_institute_id and created_by are deliberately NOT accepted
            // from the request; they are injected from the verified session
            // below. `model` and `effort` are absent for the same reason they
            // are absent from question generation: they are server-owned in
            // config/claude.php so a caller cannot select an expensive model
            // on the tenant's account.
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'status_code' => 0,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $input = $validator->validated();
        $subInstituteId = (int) $request->session()->get('sub_institute_id');

        $chapter = chapterModel::where('id', $input['chapter_id'])->first();
        if (!$chapter) {
            return response()->json([
                'success' => false,
                'status_code' => 0,
                'message' => 'Chapter not found.',
            ], 404);
        }

        // Tenant ownership, the way QuestionGenerationService gates concepts.
        if ((int) $chapter->sub_institute_id !== $subInstituteId) {
            return response()->json([
                'success' => false,
                'status_code' => 0,
                'message' => 'This chapter belongs to another institute.',
            ], 403);
        }

        if (!$this->service->handles($chapter->id)) {
            return response()->json([
                'success' => false,
                'status_code' => 0,
                'message' => 'Claude content generation is not enabled for this chapter.',
            ], 422);
        }

        // chapter_master.grade_id is nullable but content_master.grade_id is
        // not, so fall back to the standard's grade the same way
        // storeGammaContent does.
        $gradeId = $chapter->grade_id
            ?: DB::table('standard')->where('id', $chapter->standard_id)->value('grade_id');

        if (!$gradeId) {
            return response()->json([
                'success' => false,
                'status_code' => 0,
                'message' => 'Grade could not be resolved for this chapter.',
            ], 422);
        }

        $contentType = trim($input['content_type']);
        $normalized = strtolower(str_replace(['-', ' '], '_', $contentType));
        $isPresentation = in_array($normalized, ['presentation', 'teacher_training_presentation'], true);

        $result = $this->service->generate([
            'prompt' => $input['prompt'],
            'content_type' => $contentType,
            'is_presentation' => $isPresentation,
            'chapter' => $chapter,
            'chapter_name' => $chapter->chapter_name,
            'grade_id' => $gradeId,
            'concept_id' => $input['concept_id'] ?? null,
            'sub_institute_id' => $subInstituteId,
            'syear' => $request->session()->get('syear') ?? $chapter->syear,
            'created_by' => (int) $request->session()->get('user_id'),
            'user_profile_name' => $request->input('user_profile_name'),
        ]);

        return response()->json($result['body'], $result['http']);
    }
}
