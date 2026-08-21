<?php

namespace App\Http\Controllers\api\TalentManagement\Recruitment;

use App\Http\Controllers\Controller;
use App\Models\TalentManagement\TalentEvaluationForm;
use App\Models\TalentManagement\TalentInterviewSchedule;
use App\Models\TalentManagement\TalentJobApplication;
use App\Models\TalentManagement\TalentJobPosting;
use App\Models\TalentManagement\TalentOffer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Ported 1:1 from hp_erp's `App\Http\Controllers\talent\talent_jobpostingcontroller`.
 *
 * Auth: mirrors `OnboardingApiController` exactly — tenant scope comes from
 * the hydrated session (`session()->get('sub_institute_id')`), never from
 * request input. The source controller's `if ($type == "API") { validate
 * token... }` branches and its web/mobile fallback branches are dropped
 * entirely: this project's `api.session` middleware performs the real
 * authentication and hydrates the session before the controller ever runs,
 * so there is exactly one code path here instead of the source's three
 * (API / web / mobile).
 *
 * `created_by`/`updated_by`/`deleted_by` are taken from `session()->get('user_id')`,
 * never from request input — same rule the source's `ResolvesG2gActor` trait
 * enforced (token/session first, never a caller-supplied id).
 *
 * store()/update() accept both JSON and multipart bodies at the same URL:
 * this needs no special handling because `Illuminate\Http\Request::input()`
 * already reads both transparently — exactly how the source controller (which
 * never branched on content type either) behaved.
 */
class JobPostingController extends Controller
{
    /** Tenant context, taken from the JWT-hydrated session only. */
    private function subInstituteId(): int
    {
        return (int) session()->get('sub_institute_id');
    }

    private function actorId(): ?int
    {
        $userId = session()->get('user_id');

        return $userId !== null ? (int) $userId : null;
    }

    /**
     * GET job-postings
     * Ported from talent_jobpostingcontroller@index (API branch only).
     */
    public function index(Request $request)
    {
        try {
            $subInstituteId = $this->subInstituteId();

            // Auto-update expired job postings to inactive.
            DB::table('talent_job_postings')
                ->where('sub_institute_id', $subInstituteId)
                ->where('status', 'active')
                ->whereNotNull('deadline')
                ->where('deadline', '<', now())
                ->update(['status' => 'inactive', 'updated_at' => now()]);

            $talent = DB::table('talent_job_postings as a')
                ->leftJoin('hrms_departments as d', 'a.department_id', '=', 'd.id')
                ->select('a.*', 'd.department as department_name')
                ->where('a.sub_institute_id', $subInstituteId)
                ->whereNull('a.deleted_at')
                ->get();

            return response()->json([
                'message' => ' fetched successfully',
                'data' => $talent,
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST job-postings (JSON and multipart)
     * Ported from talent_jobpostingcontroller@store (API branch only).
     */
    public function store(Request $request)
    {
        $subInstituteId = $this->subInstituteId();

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'department_id' => 'required|integer',
            'location' => 'required|string|max:255',
            'employment_type' => 'required|string|max:100',
            'experience' => 'nullable|string|max:255',
            'education' => 'nullable|string|max:255',
            'priority_level' => 'nullable|string|max:100',
            'positions' => 'required|integer|min:1',
            'min_salary' => 'nullable|numeric|min:0',
            'max_salary' => 'nullable|numeric|min:0',
            'deadline' => 'nullable|date',
            'skills' => 'nullable|string',
            'certifications' => 'nullable|string',
            'benefits' => 'nullable|string',
            'description' => 'nullable|string',
            'status' => 'required|in:active,inactive',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 0,
                'message' => $validator->messages()->first(),
            ], 422);
        }

        try {
            $objtalent = new TalentJobPosting();
            $objtalent->title = $request->title;
            $objtalent->department_id = $request->department_id;
            $objtalent->location = $request->location;
            $objtalent->employment_type = $request->employment_type;
            $objtalent->experience = $request->experience;
            $objtalent->education = $request->education;
            $objtalent->priority_level = $request->priority_level;
            $objtalent->positions = $request->positions;
            $objtalent->min_salary = $request->min_salary;
            $objtalent->max_salary = $request->max_salary;
            $objtalent->deadline = $request->deadline;
            $objtalent->skills = $request->skills;
            $objtalent->certifications = $request->certifications;
            $objtalent->benefits = $request->benefits;
            $objtalent->description = $request->description;
            $objtalent->status = $request->status;
            $objtalent->sub_institute_id = $subInstituteId;
            $objtalent->created_by = $this->actorId();

            if ($objtalent->save()) {
                return response()->json(['message' => 'added successfully !!', 'data' => $objtalent], 200);
            }

            return response()->json(['message' => 'Something went wrong !!'], 500);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()]);
        }
    }

    /**
     * GET /talent/team-overview
     * Ported from talent_jobpostingcontroller@getHiringStatus (API branch only).
     */
    public function getHiringStatus(Request $request)
    {
        try {
            $subInstituteId = $this->subInstituteId();

            DB::table('talent_job_postings')
                ->where('sub_institute_id', $subInstituteId)
                ->where('status', 'active')
                ->whereNotNull('deadline')
                ->where('deadline', '<', now())
                ->update(['status' => 'inactive', 'updated_at' => now()]);

            $data = DB::select("
                SELECT
                    d.department AS department_name,
                    COUNT(DISTINCT jp.id) AS total_positions,
                    COUNT(CASE WHEN ja.status = 'hired' THEN ja.id END) AS hired
                FROM talent_job_postings jp
                LEFT JOIN hrms_departments d
                    ON jp.department_id = d.id
                LEFT JOIN talent_job_applications ja
                    ON ja.job_id = jp.id
                WHERE jp.sub_institute_id = ?
                GROUP BY d.id, d.department
            ", [$subInstituteId]);

            $recentTeamUpdates = [];

            $recentHires = DB::select("
                SELECT CONCAT(ja.first_name, ' ', ja.last_name) as candidate_name, d.department
                FROM talent_job_applications ja
                JOIN talent_job_postings jp ON ja.job_id = jp.id
                JOIN hrms_departments d ON jp.department_id = d.id
                WHERE ja.status = 'hired' AND ja.sub_institute_id = ? AND ja.updated_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                ORDER BY ja.updated_at DESC
                LIMIT 5
            ", [$subInstituteId]);

            foreach ($recentHires as $hire) {
                $recentTeamUpdates[] = $hire->candidate_name . ' joined ' . $hire->department . ' team';
            }

            $openPositionsCount = DB::select("
                SELECT COUNT(*) as count FROM talent_job_postings
                WHERE status = 'active' AND sub_institute_id = ? AND deleted_at IS NULL
            ", [$subInstituteId])[0]->count;

            if ($openPositionsCount > 0) {
                $recentTeamUpdates[] = $openPositionsCount . ' position(s) still open';
            }

            $attentionPositions = DB::select("
                SELECT title FROM talent_job_postings
                WHERE status = 'active' AND sub_institute_id = ? AND deadline <= DATE_ADD(NOW(), INTERVAL 7 DAY) AND deleted_at IS NULL
                LIMIT 3
            ", [$subInstituteId]);

            foreach ($attentionPositions as $pos) {
                $recentTeamUpdates[] = $pos->title . ' role needs attention';
            }

            $upcomingInterviews = DB::select("
                SELECT COUNT(*) as count FROM talent_interview_schedules
                WHERE sub_institute_id = ? AND interview_date >= CURDATE() AND status = 'scheduled'
            ", [$subInstituteId])[0]->count;

            if ($upcomingInterviews > 0) {
                $recentTeamUpdates[] = $upcomingInterviews . ' interview(s) scheduled';
            }

            $tomorrowInterviews = DB::select("
                SELECT jp.title FROM talent_interview_schedules tis
                JOIN talent_job_postings jp ON tis.job_id = jp.id
                WHERE tis.sub_institute_id = ? AND tis.interview_date = DATE_ADD(CURDATE(), INTERVAL 1 DAY) AND tis.status = 'scheduled'
                LIMIT 3
            ", [$subInstituteId]);

            foreach ($tomorrowInterviews as $interview) {
                $recentTeamUpdates[] = $interview->title . ' candidate tomorrow';
            }

            return response()->json([
                'message' => 'Hiring status fetched successfully',
                'data' => $data,
                'recent_team_updates' => $recentTeamUpdates,
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * PUT job-postings/{id} (JSON and multipart)
     * Ported from talent_jobpostingcontroller@update.
     */
    public function update(Request $request, string $id)
    {
        $subInstituteId = $this->subInstituteId();

        $exists = TalentJobPosting::where([
            'sub_institute_id' => $subInstituteId,
            'id' => $id,
        ])->exists();

        if (!$exists) {
            return response()->json([
                'message' => 'No talent record found for this department',
                'data' => $id,
            ], 404);
        }

        $updated = TalentJobPosting::where([
            'sub_institute_id' => $subInstituteId,
            'id' => $id,
        ])->update([
            'title' => $request->title,
            'location' => $request->location,
            'employment_type' => $request->employment_type,
            'experience' => $request->experience,
            'department_id' => $request->department_id,
            'education' => $request->education,
            'priority_level' => $request->priority_level,
            'positions' => $request->positions,
            'min_salary' => $request->min_salary,
            'max_salary' => $request->max_salary,
            'deadline' => $request->deadline,
            'skills' => $request->skills,
            'certifications' => $request->certifications,
            'benefits' => $request->benefits,
            'description' => $request->description,
            'status' => $request->status,
            'updated_by' => $this->actorId(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => $updated ? 'Updated successfully' : 'Failed to update',
            'data' => $id,
        ], $updated ? 200 : 400);
    }

    /**
     * DELETE job-postings/{id}
     * Ported from talent_jobpostingcontroller@destroy (API branch only).
     * Cascades a soft delete onto related evaluation/interview/application/offer rows, same as source.
     */
    public function destroy(Request $request, string $id)
    {
        $subInstituteId = $this->subInstituteId();

        try {
            $exists = TalentJobPosting::where([
                'id' => $id,
                'sub_institute_id' => $subInstituteId,
            ])->whereNull('deleted_at')->exists();

            if (!$exists) {
                return response()->json(['message' => 'Job posting not found or already deleted'], 404);
            }

            TalentEvaluationForm::where('job_id', $id)->whereNull('deleted_at')->update([
                'deleted_at' => now(),
            ]);

            TalentInterviewSchedule::where('job_id', $id)->whereNull('deleted_at')->update([
                'deleted_at' => now(),
            ]);

            TalentJobApplication::where('job_id', $id)->whereNull('deleted_at')->update([
                'deleted_at' => now(),
            ]);

            TalentOffer::where('job_id', $id)->whereNull('deleted_at')->update([
                'deleted_at' => now(),
            ]);

            $delete = TalentJobPosting::where('id', $id)->update([
                'deleted_at' => now(),
                'deleted_by' => $this->actorId(),
            ]);

            if ($delete) {
                return response()->json(['message' => 'Job posting and related data deleted successfully'], 200);
            }

            return response()->json(['message' => 'Failed to delete job posting'], 400);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // ------------------------------------------------------------------
    // Response helpers - same shape as OnboardingApiController's own.
    // Not used above (every endpoint here has a hard external contract with
    // the existing frontend and keeps hp_erp's exact response shape), but
    // kept available per this project's controller convention.
    // ------------------------------------------------------------------

    private function success(string $message, array $data)
    {
        return response()->json(['status' => 1, 'status_code' => 1, 'message' => $message, 'data' => $data]);
    }

    private function failure(string $message, int $code = 422, $errors = null)
    {
        return response()->json([
            'status' => 0,
            'status_code' => 0,
            'message' => $message,
            'errors' => $errors,
            'data' => [],
        ], $code);
    }
}
