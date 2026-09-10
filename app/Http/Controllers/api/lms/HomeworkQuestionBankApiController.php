<?php

namespace App\Http\Controllers\api\lms;

use App\Http\Controllers\Controller;
use App\Models\lms\lmsQuestionMasterModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * "Generate homework from the question bank" -- teacher-facing lookups over
 * question_type_master / lms_question_master used to build a question_bank
 * source_type homework row (see StudentHomeworkApiController::store()).
 *
 * Additive to the Homework module; does not touch the Exam module's
 * ApiLmsCourseController (getLmsQuestions/chapters/getChapterConcepts), which
 * this deliberately does not modify or duplicate for chapters -- the frontend
 * reuses `POST lms-chapters` for that.
 *
 * Mirrors HomeworkSubmissionApiController's conventions: { status_code,
 * message, data } envelope, api.session + staff.only middleware, snake_case
 * data (this codebase's controllers return raw/snake_case column data, not
 * camelCase -- see HomeworkSubmissionApiController::detail()'s "homework"
 * payload), and the sub_institute_id = 1 shared/global-row OR-fallback used
 * throughout ApiLmsCourseController (e.g. getChapterConcepts).
 */
class HomeworkQuestionBankApiController extends Controller
{
    private function fail(string $message, int $status = 422, array $extra = []): JsonResponse
    {
        return response()->json(array_merge([
            'status_code' => 0,
            'message' => $message,
        ], $extra), $status);
    }

    /**
     * POST lms-homework/question-bank/types
     * Active question types, scoped to the caller's institute plus the
     * shared/global sub_institute_id = 1 rows.
     */
    public function questionTypes(Request $request): JsonResponse
    {
        $sub_institute_id = $request->input('sub_institute_id');
        if (!$sub_institute_id) {
            try {
                $sub_institute_id = $request->session()->get('sub_institute_id');
            } catch (\Throwable $e) {
                $sub_institute_id = null;
            }
        }

        $query = DB::table('question_type_master')->where('status', 1);

        if ($sub_institute_id) {
            $query->where(function ($q) use ($sub_institute_id) {
                $q->where('sub_institute_id', $sub_institute_id)
                    ->orWhere('sub_institute_id', 1);
            });
        }

        $types = $query->orderBy('question_type')->get(['id', 'question_type', 'status', 'sub_institute_id', 'syear'])->toArray();

        return response()->json([
            'status_code' => 1,
            'message' => 'SUCCESS',
            'data' => $types,
            'total' => count($types),
        ], 200);
    }

    /**
     * POST lms-homework/question-bank/questions
     * Question-bank questions filtered by subject (required), standard,
     * chapter(s) and question type(s) -- the filters getLmsQuestions (Exam
     * module) does not support, without touching that controller.
     */
    public function questions(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'subject_id' => 'required|numeric',
            'standard_id' => 'nullable|numeric',
            'chapter_id' => 'nullable|array',
            'chapter_id.*' => 'integer',
            'question_type_id' => 'nullable|array',
            'question_type_id.*' => 'integer',
        ]);

        if ($validator->fails()) {
            return $this->fail('Validation failed', 422, ['errors' => $validator->errors()]);
        }

        $sub_institute_id = $request->input('sub_institute_id');
        if (!$sub_institute_id) {
            try {
                $sub_institute_id = $request->session()->get('sub_institute_id');
            } catch (\Throwable $e) {
                $sub_institute_id = null;
            }
        }
        // Note: lms_question_master has no `syear` column (see migration
        // 2023_03_05_115658_create_lms_question_master_table.php), so unlike
        // sub_institute_id, syear is not part of this query's scoping.

        $subject_id = $request->input('subject_id');
        $standard_id = $request->input('standard_id');

        $chapter_ids = $request->input('chapter_id', []);
        if (is_string($chapter_ids)) {
            $chapter_ids = $chapter_ids !== '' ? explode(',', $chapter_ids) : [];
        }
        $chapter_ids = array_values(array_filter(array_map('intval', (array) $chapter_ids)));

        $question_type_ids = $request->input('question_type_id', []);
        if (is_string($question_type_ids)) {
            $question_type_ids = $question_type_ids !== '' ? explode(',', $question_type_ids) : [];
        }
        $question_type_ids = array_values(array_filter(array_map('intval', (array) $question_type_ids)));

        $query = lmsQuestionMasterModel::query()->where('subject_id', $subject_id);

        if ($standard_id) {
            $query->where('standard_id', $standard_id);
        }

        if (!empty($chapter_ids)) {
            $query->whereIn('chapter_id', $chapter_ids);
        }

        if (!empty($question_type_ids)) {
            $query->whereIn('question_type_id', $question_type_ids);
        }

        if ($sub_institute_id) {
            $query->where(function ($q) use ($sub_institute_id) {
                $q->where('sub_institute_id', $sub_institute_id)
                    ->orWhere('sub_institute_id', 1);
            });
        }

        $questions = $query->orderByDesc('id')->get([
            'id', 'question_title', 'description', 'question_type_id', 'points', 'chapter_id',
        ])->toArray();

        return response()->json([
            'status_code' => 1,
            'message' => 'SUCCESS',
            'data' => $questions,
            'total' => count($questions),
            'subject_id' => $subject_id,
            'standard_id' => $standard_id,
            'chapter_ids' => $chapter_ids,
            'question_type_ids' => $question_type_ids,
        ], 200);
    }
}
