<?php

namespace App\Http\Controllers\lms\counselling;

use App\CareerIntelligence\CareerCounsellingNarrator;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * Career Counselling — AI counsellor narrative.
 *
 * Grounds "an AI counsellor retrieving the student's skill/interest graph"
 * in what actually exists: CaiCoreService::evaluate()'s real career-graph
 * evaluation plus the student's real concept-mastery state from the Set
 * Coherence Map — never the HasInterest/HasWorkValue edges a prior pass
 * confirmed do not exist in the live graph. See CareerCounsellingNarrator
 * for the full grounding rationale.
 *
 * Auth pattern mirrors CareerRecommendationController::recommend() and
 * lmsCounsellingController::studentCareerEvidence() exactly: identity comes
 * from the session, and only an admin (is_admin 1 or 2) may pass a
 * different student_id — never trusted from the request for anyone else.
 */
class CareerCounsellingNarrativeController extends Controller
{
    public function __construct(private readonly CareerCounsellingNarrator $narrator)
    {
    }

    public function narrate(Request $request)
    {
        $sessionStudentId = $request->session()->get('user_id');
        $syear = $request->session()->get('syear');
        $subInstituteId = $request->session()->get('sub_institute_id');

        if (empty($sessionStudentId) || empty($syear)) {
            return response()->json(['status_code' => 0, 'message' => 'Unauthenticated.'], 401);
        }

        $requestedStudentId = $request->query('student_id');
        $studentId = (string) $sessionStudentId;

        if (! empty($requestedStudentId) && (string) $requestedStudentId !== (string) $sessionStudentId) {
            $isAdmin = (int) $request->session()->get('is_admin');

            if ($isAdmin !== 1 && $isAdmin !== 2) {
                return response()->json([
                    'status_code' => 0,
                    'message' => 'You do not have permission to view this student\'s career counselling narrative.',
                ], 403);
            }

            $studentId = (string) $requestedStudentId;
        }

        $result = $this->narrator->narrate(
            (int) $studentId,
            $subInstituteId !== null ? (int) $subInstituteId : null,
            (string) $syear
        );

        return response()->json([
            'status_code' => 1,
            'message' => 'SUCCESS',
            'data' => $result,
        ]);
    }
}
