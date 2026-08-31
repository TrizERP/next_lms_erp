<?php

namespace App\Http\Controllers\api\TalentManagement\Recruitment;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\TalentManagement\TalentInterviewSchedule;
use App\Models\TalentManagement\TalentJobApplication;
use App\Models\TalentManagement\TalentJobPosting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Ported 1:1 from hp_erp's `App\Http\Controllers\talent\talent_interviewschedulescontroller`.
 *
 * Auth/tenant: mirrors `OnboardingApiController` — `session()->get('sub_institute_id')`,
 * never request input. `created_by`/`updated_by` come from `session()->get('user_id')`.
 *
 * `index()` backs `GET /interview-details` (hp_erp binds
 * `Route::get('/interview-details', [talent_interviewschedulescontroller::class, 'index'])`
 * — NOT the source's own `getInterviewDetails()` method, which exists in the
 * source file but is never bound to any route there. Both are ported here for
 * completeness (see class docblock note on `getInterviewDetails`).
 */
class InterviewScheduleController extends Controller
{
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
     * GET /interview-details
     * Ported from talent_interviewschedulescontroller@index (API branch only).
     * This is the method hp_erp actually binds to `/interview-details`.
     *
     * hp_erp's original `select('*')` never joined `talent_interview_panel`,
     * so `panel_id` came through but `panel_name` never did - every scheduled
     * interview looked unassigned downstream (Submit Interview Feedback /
     * Reschedule) no matter what panel was actually stored. Added a left join
     * for `panel_name` only; `panel_id` and every other column are untouched.
     *
     * Also dropped hp_erp's `where('status', 'Scheduled')` filter. Submitting
     * feedback (FeedbackController::storeFeedback) flips this same row's
     * status to 'Completed', so with that filter in place an interview
     * vanished from this endpoint the moment feedback was recorded for it -
     * the candidate profile's interview history (which reads date/time/
     * location/panel from here, joined against submitted feedback by
     * candidate+job+panel) could never resolve those fields for any
     * interview that actually had feedback. The frontend already maps
     * Scheduled/Completed/Cancelled into distinct display states, so
     * returning every status here was the behavior it was already built for.
     */
    public function index(Request $request)
    {
        try {
            $subInstituteId = $this->subInstituteId();

            $interviewSchedules = DB::table('talent_interview_schedules as a')
                ->leftJoin('talent_interview_panel as tip', 'a.panel_id', '=', 'tip.id')
                ->select('a.*', 'tip.panel_name')
                ->where('a.sub_institute_id', $subInstituteId)
                ->get();

            return response()->json([
                'message' => ' fetched successfully',
                'data' => $interviewSchedules,
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST interview-schedules
     * Ported from talent_interviewschedulescontroller@store (API branch only).
     */
    public function store(Request $request)
    {
        $subInstituteId = $this->subInstituteId();

        if (is_string($request->interviewer_id)) {
            $decoded = json_decode($request->interviewer_id, true);
            if (is_array($decoded)) {
                $request->merge(['interviewer_id' => array_map('intval', $decoded)]);
            } else {
                $request->merge(['interviewer_id' => array_map('intval', array_map('trim', explode(',', $request->interviewer_id)))]);
            }
        }

        $validator = Validator::make($request->all(), [
            'job_id' => 'required|integer|exists:talent_job_postings,id',
            'applicant_id' => 'required|string|max:255',
            'round_no' => 'nullable|string|max:255',
            'interview_date' => 'nullable|date|after_or_equal:today',
            'time' => 'nullable|string|max:255',
            'duration' => 'nullable|integer',
            'location' => 'nullable|string|max:255',
            'interviewer_id' => 'required|array',
            'status' => 'required|in:Scheduled,Completed,Under Review,Pending Review,Rejected,Selected,Accepted,active',
            'rating' => 'nullable|string|max:255',
            'feedback' => 'nullable|string|max:100',
            'additional_notes' => 'nullable|string|max:1000',
            'panel_id' => 'nullable|integer|exists:talent_interview_panel,id',
        ], [
            'interview_date.after_or_equal' => 'Previous date is not allowed. Please select today or a future date.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 0,
                'message' => $validator->messages()->first(),
            ], 422);
        }

        $candidate = TalentJobApplication::where('id', $request->applicant_id)
            ->where('sub_institute_id', $subInstituteId)
            ->first();
        if (!$candidate) {
            return response()->json(['message' => 'Candidate not found.'], 404);
        }
        if ($candidate->hasReachedOfferOrHiredStage()) {
            return response()->json([
                'message' => 'An interview cannot be scheduled after an offer has been sent or the candidate has been hired.',
            ], 422);
        }

        try {
            $objtalent = new TalentInterviewSchedule();
            $objtalent->job_id = $request->job_id;
            $objtalent->applicant_id = $request->applicant_id;
            $objtalent->round_no = ((int) TalentInterviewSchedule::where(
                'applicant_id',
                $request->applicant_id
            )->max('round_no')) + 1;
            $objtalent->interview_date = $request->interview_date;
            $objtalent->time = $request->time;
            $objtalent->duration = $request->duration;
            $objtalent->location = $request->location;
            $objtalent->interviewer_id = $request->interviewer_id;
            $objtalent->status = $request->status;
            $objtalent->rating = $request->rating;
            $objtalent->feedback = $request->feedback;
            $objtalent->additional_notes = $request->additional_notes;
            $objtalent->sub_institute_id = $subInstituteId;
            $objtalent->created_by = $this->actorId();
            $objtalent->panel_id = $request->panel_id;

            if ($objtalent->save()) {
                AuditLog::record([
                    'module' => 'talent_management',
                    'action' => 'interview_scheduled',
                    'entity_type' => 'interview_schedule',
                    'entity_id' => $objtalent->id,
                    'new_values' => [
                        'job_id' => $objtalent->job_id,
                        'applicant_id' => $objtalent->applicant_id,
                        'round_no' => $objtalent->round_no,
                        'interview_date' => $objtalent->interview_date,
                        'status' => $objtalent->status,
                        'panel_id' => $objtalent->panel_id,
                    ],
                ]);

                return response()->json(['message' => 'added successfully !!', 'data' => $objtalent], 200);
            }

            return response()->json(['message' => 'Something went wrong !!'], 500);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()]);
        }
    }

    /**
     * PUT interview-schedules/{id}
     * Ported from talent_interviewschedulescontroller@update (API branch only).
     */
    public function update(Request $request, $id)
    {
        $subInstituteId = $this->subInstituteId();

        if (is_string($request->interviewer_id)) {
            $decoded = json_decode($request->interviewer_id, true);
            if (is_array($decoded)) {
                $request->merge(['interviewer_id' => array_map('intval', $decoded)]);
            } else {
                $request->merge(['interviewer_id' => array_map('intval', array_map('trim', explode(',', $request->interviewer_id)))]);
            }
        }

        $validator = Validator::make($request->all(), [
            'job_id' => 'nullable|integer|exists:talent_job_postings,id',
            'applicant_id' => 'nullable|string|max:255',
            'round_no' => 'nullable|string|max:255',
            'interview_date' => 'nullable|date',
            'time' => 'nullable|string|max:255',
            'duration' => 'nullable|integer',
            'location' => 'nullable|string|max:255',
            'interviewer_id' => 'required|array',
            'status' => 'required|in:Scheduled,Completed,Under Review,Pending Review,Rejected,Selected,Accepted,active',
            'rating' => 'nullable|string|max:255',
            'feedback' => 'nullable|string|max:100',
            'additional_notes' => 'nullable|string|max:1000',
            'panel_id' => 'nullable|integer|exists:talent_interview_panel,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 0,
                'message' => $validator->messages()->first(),
            ], 422);
        }

        try {
            $interviewSchedule = TalentInterviewSchedule::find($id);

            if (!$interviewSchedule) {
                return response()->json(['message' => 'interview schedules not found'], 404);
            }

            if ($request->filled('job_id')) {
                $jobData = TalentJobPosting::find($request->job_id);
                if ($jobData) {
                    $interviewSchedule->job_id = $jobData->id;
                }
            }

            $interviewSchedule->applicant_id = $request->applicant_id ?? $interviewSchedule->applicant_id;
            $interviewSchedule->round_no = $request->round_no ?? $interviewSchedule->round_no;
            $interviewSchedule->interview_date = $request->interview_date ?? $interviewSchedule->interview_date;
            $interviewSchedule->time = $request->time ?? $interviewSchedule->time;
            $interviewSchedule->duration = $request->duration ?? $interviewSchedule->duration;
            $interviewSchedule->location = $request->location ?? $interviewSchedule->location;
            $interviewSchedule->interviewer_id = $request->interviewer_id ?? $interviewSchedule->interviewer_id;
            $interviewSchedule->status = $request->status ?? $interviewSchedule->status;
            $interviewSchedule->rating = $request->rating ?? $interviewSchedule->rating;
            $interviewSchedule->feedback = $request->feedback ?? $interviewSchedule->feedback;
            $interviewSchedule->additional_notes = $request->additional_notes ?? $interviewSchedule->additional_notes;
            $interviewSchedule->panel_id = $request->panel_id ?? $interviewSchedule->panel_id;

            $allowedStatuses = [
                'Scheduled', 'Completed', 'Under Review', 'Pending Review',
                'Rejected', 'Selected', 'Accepted', 'active',
            ];

            if ($request->filled('status') && in_array($request->status, $allowedStatuses)) {
                $interviewSchedule->status = $request->status;
            }

            $interviewSchedule->updated_by = $this->actorId();
            $interviewSchedule->sub_institute_id = $subInstituteId;

            if ($interviewSchedule->save()) {
                AuditLog::record([
                    'module' => 'talent_management',
                    'action' => 'interview_schedule_updated',
                    'entity_type' => 'interview_schedule',
                    'entity_id' => $interviewSchedule->id,
                    'new_values' => [
                        'status' => $interviewSchedule->status,
                        'interview_date' => $interviewSchedule->interview_date,
                        'panel_id' => $interviewSchedule->panel_id,
                    ],
                ]);

                return response()->json([
                    'message' => 'interview schedules updated successfully!',
                    'data' => $interviewSchedule,
                ], 200);
            }

            return response()->json(['message' => 'Update failed!'], 500);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * PUT /interview-schedules (no id in the path; id comes from the body)
     * Ported from talent_interviewschedulescontroller@customUpdate (API branch only).
     */
    public function customUpdate(Request $request)
    {
        $subInstituteId = $this->subInstituteId();

        if (is_string($request->interviewer_id)) {
            $decoded = json_decode($request->interviewer_id, true);
            if (is_array($decoded)) {
                $request->merge(['interviewer_id' => array_map('intval', $decoded)]);
            } else {
                $request->merge(['interviewer_id' => array_map('intval', array_map('trim', explode(',', $request->interviewer_id)))]);
            }
        }

        $validator = Validator::make($request->all(), [
            'id' => 'required|integer',
            'round_no' => 'nullable|string|max:255',
            'interview_date' => 'nullable|date',
            'time' => 'nullable|string|max:255',
            'duration' => 'nullable|integer',
            'location' => 'nullable|string|max:255',
            'interviewer_id' => 'required|array',
            'status' => 'required|in:Scheduled,Completed,Under Review,Pending Review,Rejected,Selected,Accepted,active',
            'rating' => 'nullable|string|max:255',
            'feedback' => 'nullable|string|max:100',
            'additional_notes' => 'nullable|string|max:1000',
            'panel_id' => 'nullable|integer|exists:talent_interview_panel,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 0,
                'message' => $validator->messages()->first(),
            ], 422);
        }

        try {
            $interviewSchedule = TalentInterviewSchedule::find($request->id);

            if (!$interviewSchedule) {
                return response()->json(['message' => 'Interview schedule not found'], 404);
            }

            $interviewSchedule->round_no = $request->round_no ?? $interviewSchedule->round_no;
            $interviewSchedule->interview_date = $request->interview_date ?? $interviewSchedule->interview_date;
            $interviewSchedule->time = $request->time ?? $interviewSchedule->time;
            $interviewSchedule->duration = $request->duration ?? $interviewSchedule->duration;
            $interviewSchedule->location = $request->location ?? $interviewSchedule->location;
            $interviewSchedule->interviewer_id = $request->interviewer_id ?? $interviewSchedule->interviewer_id;
            $interviewSchedule->status = $request->status ?? $interviewSchedule->status;
            $interviewSchedule->rating = $request->rating ?? $interviewSchedule->rating;
            $interviewSchedule->feedback = $request->feedback ?? $interviewSchedule->feedback;
            $interviewSchedule->additional_notes = $request->additional_notes ?? $interviewSchedule->additional_notes;
            $interviewSchedule->panel_id = $request->panel_id ?? $interviewSchedule->panel_id;

            $allowedStatuses = [
                'Scheduled', 'Completed', 'Under Review', 'Pending Review',
                'Rejected', 'Selected', 'Accepted', 'active',
            ];

            if ($request->filled('status') && in_array($request->status, $allowedStatuses)) {
                $interviewSchedule->status = $request->status;
            }

            $interviewSchedule->updated_by = $this->actorId();
            $interviewSchedule->sub_institute_id = $subInstituteId;

            if ($interviewSchedule->save()) {
                AuditLog::record([
                    'module' => 'talent_management',
                    'action' => 'interview_schedule_updated',
                    'entity_type' => 'interview_schedule',
                    'entity_id' => $interviewSchedule->id,
                    'new_values' => [
                        'status' => $interviewSchedule->status,
                        'interview_date' => $interviewSchedule->interview_date,
                        'panel_id' => $interviewSchedule->panel_id,
                    ],
                ]);

                return response()->json([
                    'message' => 'Interview schedule updated successfully!',
                    'data' => $interviewSchedule,
                ], 200);
            }

            return response()->json(['message' => 'Update failed!'], 500);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Ported from talent_interviewschedulescontroller@getInterviewDetails.
     *
     * NOT bound to any route in hp_erp (only `index()` above is, at
     * `/interview-details`) - dead code in the source. Ported anyway for
     * completeness/traceability but deliberately left unregistered here too,
     * matching hp_erp's actual routing exactly. See the porting report.
     */
    public function getInterviewDetails(Request $request)
    {
        try {
            $subInstituteId = $this->subInstituteId();

            $candidates = DB::table('talent_job_applications as tja')
                ->leftJoin('talent_interview_schedules as tis', function ($join) {
                    $join->on('tja.id', '=', 'tis.applicant_id')
                        ->whereRaw('tis.round_no = (SELECT MAX(round_no) FROM talent_interview_schedules WHERE applicant_id = tja.id)');
                })
                ->leftJoin('talent_job_postings as tjp', 'tja.job_id', '=', 'tjp.id')
                ->leftJoin('talent_interview_panel as tip', 'tis.panel_id', '=', 'tip.id')
                ->select(
                    'tja.id as candidate_id',
                    DB::raw("CONCAT_WS(' ', tja.first_name, tja.middle_name, tja.last_name) AS candidate_name"),
                    'tja.email',
                    'tjp.id as position_id',
                    'tjp.title as position',
                    'tja.status',
                    'tis.status as stage',
                    'tja.applied_date',
                    DB::raw("CONCAT(tis.interview_date,' ',tis.time) AS next_interview"),
                    'tis.rating as score',
                    'tis.panel_id',
                    'tis.id as scheduled_id',
                    'tip.panel_name',
                    'tis.location',
                    'tis.duration'
                )
                ->where('tja.sub_institute_id', $subInstituteId)
                ->where('tis.status', 'Scheduled')
                ->where('tja.status', '!=', 'Hired')
                ->get();

            return response()->json([
                'message' => 'Candidate details fetched successfully',
                'data' => $candidates,
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /candidate-pipeline
     * Ported from talent_interviewschedulescontroller@candidatepipeline (API branch only).
     * Not in the ground-truth endpoint list, but part of this same source
     * controller - ported and registered for completeness.
     */
    public function candidatepipeline(Request $request)
    {
        try {
            $subInstituteId = $this->subInstituteId();

            $stages = [
                'Application Review' => ['status' => ['applied', 'under_review']],
                'Phone Screening' => ['status' => ['shortlisted', 'phone_screening']],
                'Technical Interview' => ['round' => 'technical'],
                'Final Interview' => ['round' => 'final'],
                'Offer Extended' => ['status' => ['offered']],
            ];

            $data = [];
            $totalApplications = DB::table('talent_job_applications')
                ->where('sub_institute_id', $subInstituteId)
                ->whereNull('deleted_at')
                ->count();

            foreach ($stages as $label => $criteria) {
                $query = DB::table('talent_job_applications as ja')
                    ->where('ja.sub_institute_id', $subInstituteId)
                    ->whereNull('ja.deleted_at');

                if (isset($criteria['status'])) {
                    $query->whereIn('ja.status', $criteria['status']);
                } elseif (isset($criteria['round'])) {
                    $query->join('talent_interview_schedules as tis', 'ja.id', '=', 'tis.applicant_id')
                        ->where('tis.round_no', $criteria['round'])
                        ->where('tis.status', 'scheduled');
                }

                $count = $query->count();

                $previousQuery = clone $query;
                $previousCount = $previousQuery->where('ja.created_at', '<', now()->subDays(7))->count();
                $change = $previousCount > 0 ? (($count - $previousCount) / $previousCount) * 100 : 0;

                $data[] = [
                    'stage' => $label,
                    'count' => $count,
                    'change' => ($change > 0 ? '+' : '') . round($change, 1) . '%',
                ];
            }

            $hiredCount = DB::table('talent_job_applications')
                ->where('sub_institute_id', $subInstituteId)
                ->where('status', 'hired')
                ->whereNull('deleted_at')
                ->count();
            $conversionRate = $totalApplications > 0 ? round(($hiredCount / $totalApplications) * 100, 1) . '%' : '0%';

            $avgTimeToHire = DB::select("
                SELECT AVG(DATEDIFF(ja.updated_at, ja.created_at)) as avg_days
                FROM talent_job_applications ja
                WHERE ja.sub_institute_id = ? AND ja.status = 'hired' AND ja.updated_at IS NOT NULL
            ", [$subInstituteId]);

            $averageTimeToHire = $avgTimeToHire[0]->avg_days ? round($avgTimeToHire[0]->avg_days) . ' days' : 'N/A';

            return response()->json([
                'message' => 'Candidate pipeline fetched successfully',
                'data' => [
                    'pipeline' => $data,
                    'conversion_rate' => $conversionRate,
                    'average_time_to_hire' => $averageTimeToHire,
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
