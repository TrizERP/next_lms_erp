<?php

namespace App\Http\Controllers\api\lms;

use App\Http\Controllers\Controller;
use App\Services\Curriculum\OutcomeAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * Curriculum Outcomes & Delivery Analytics - the teacher-facing
 * EXPECTED -> PLANNED -> DELIVERED -> ASSESSED -> ACHIEVED -> GAP chain for
 * one curriculum. Sibling of CurriculumPlanningApiController; sits outside
 * the session middleware the same way, scoped by an explicit
 * sub_institute_id in every request instead.
 */
class CurriculumOutcomesApiController extends Controller
{
    public function __construct(private OutcomeAnalyticsService $analytics)
    {
    }

    /**
     * GET|POST /api/intelligence/curriculum-outcomes
     */
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'sub_institute_id' => 'required|integer',
            'syear'            => 'required',
            'standard_id'      => 'required|integer',
            'subject_id'       => 'required|integer',
            'curriculum_id'    => 'required|integer',
            'term_id'          => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $filters = $validator->validated();

        try {
            if (!$this->curriculumBelongsTo($filters)) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Curriculum not found for this institute/standard/subject',
                    'data'    => null,
                ], 404);
            }

            $data = $this->analytics->summary($filters);
            $data['curriculum'] = $this->curriculumHeader($filters);

            return response()->json([
                'status'  => true,
                'message' => 'Curriculum outcomes data found',
                'data'    => $data,
            ], 200);
        } catch (Throwable $e) {
            Log::error('CurriculumOutcomes summary failed: ' . $e->getMessage(), [
                'filters' => $filters,
                'trace'   => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status'  => false,
                'message' => 'Something went wrong while fetching curriculum outcomes data',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET|POST /api/intelligence/curriculum-outcomes/outcome
     */
    public function outcomeDetail(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'sub_institute_id' => 'required|integer',
            'syear'            => 'required',
            'standard_id'      => 'nullable|integer',
            'curriculum_id'    => 'required|integer',
            'outcome_id'       => 'required|integer',
            'term_id'          => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $filters = $validator->validated();

        try {
            if (!$this->curriculumBelongsToInstitute($filters)) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Curriculum not found for this institute',
                    'data'    => null,
                ], 404);
            }

            $detail = $this->analytics->detail((int) $filters['outcome_id'], $filters);

            if ($detail === null) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Outcome not found for this curriculum',
                    'data'    => null,
                ], 404);
            }

            return response()->json([
                'status'  => true,
                'message' => 'Outcome detail found',
                'data'    => $detail,
            ], 200);
        } catch (Throwable $e) {
            Log::error('CurriculumOutcomes detail failed: ' . $e->getMessage(), [
                'filters' => $filters,
                'trace'   => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status'  => false,
                'message' => 'Something went wrong while fetching outcome detail',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * A bare curriculum_id must not read across tenants: confirm it
     * actually belongs to the given institute/standard/subject before any
     * query runs against it.
     */
    private function curriculumBelongsTo(array $filters): bool
    {
        return DB::table('lms_curriculum')
            ->where('id', $filters['curriculum_id'])
            ->where('sub_institute_id', $filters['sub_institute_id'])
            ->where('standard_id', $filters['standard_id'])
            ->where('subject_id', $filters['subject_id'])
            ->exists();
    }

    private function curriculumBelongsToInstitute(array $filters): bool
    {
        return DB::table('lms_curriculum')
            ->where('id', $filters['curriculum_id'])
            ->where('sub_institute_id', $filters['sub_institute_id'])
            ->exists();
    }

    private function curriculumHeader(array $filters): array
    {
        $curriculum = DB::table('lms_curriculum')
            ->where('id', $filters['curriculum_id'])
            ->first(['id', 'curriculum_name', 'standard_id', 'subject_id', 'syear']);

        return [
            'curriculum_id'   => $curriculum->id,
            'curriculum_name' => $curriculum->curriculum_name,
            'standard_id'     => $curriculum->standard_id,
            'subject_id'      => $curriculum->subject_id,
            'syear'           => $curriculum->syear,
        ];
    }
}
