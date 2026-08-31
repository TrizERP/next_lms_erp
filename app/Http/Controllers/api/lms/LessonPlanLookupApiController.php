<?php

namespace App\Http\Controllers\api\lms;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class LessonPlanLookupApiController extends Controller
{
    /**
     * Chapters available for a subject's curriculum, for the "Add lesson"
     * chapter/topic picker. Chained lms_curriculum -> lms_units -> chapter_master.
     *
     * GET|POST /api/intelligence/lesson-plan-lookup/chapters
     */
    public function chapters(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'sub_institute_id' => 'required|integer',
            'syear'            => 'required',
            'standard_id'      => 'required|integer',
            'subject_id'       => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $filters = $validator->validated();

        try {
            $curriculumId = DB::table('lms_curriculum')
                ->where('sub_institute_id', $filters['sub_institute_id'])
                ->where('standard_id', $filters['standard_id'])
                ->where('subject_id', $filters['subject_id'])
                ->where('syear', $filters['syear'])
                ->value('id');

            $chapters = collect();
            if ($curriculumId) {
                $unitIds = DB::table('lms_units')->where('curriculum_id', $curriculumId)->orderBy('unit_number')->pluck('id');
                $chapters = DB::table('chapter_master')
                    ->whereIn('unit_id', $unitIds)
                    ->orderBy('sort_order')
                    ->get(['id as chapter_id', 'chapter_name']);
            }

            return response()->json([
                'status'  => true,
                'message' => $chapters->isEmpty() ? 'No chapters found - use free text instead' : 'Chapters found',
                'data'    => $chapters,
            ], 200);
        } catch (Throwable $e) {
            Log::error('LessonPlanLookup chapters fetch failed: ' . $e->getMessage(), ['filters' => $filters]);

            return response()->json(['status' => false, 'message' => 'Something went wrong while fetching chapters', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Timetable periods (Period 1, Period 2, ...) for the "Add lesson" period slot picker.
     *
     * GET|POST /api/intelligence/lesson-plan-lookup/periods
     */
    public function periods(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'sub_institute_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $filters = $validator->validated();

        try {
            $periods = DB::table('period')
                ->where('sub_institute_id', $filters['sub_institute_id'])
                ->where('status', 1)
                ->orderBy('sort_order')
                ->get(['id as period_id', 'title', 'short_name', 'start_time', 'end_time']);

            return response()->json([
                'status'  => true,
                'message' => $periods->isEmpty() ? 'No periods found' : 'Periods found',
                'data'    => $periods,
            ], 200);
        } catch (Throwable $e) {
            Log::error('LessonPlanLookup periods fetch failed: ' . $e->getMessage(), ['filters' => $filters]);

            return response()->json(['status' => false, 'message' => 'Something went wrong while fetching periods', 'error' => $e->getMessage()], 500);
        }
    }
}
