<?php

namespace App\Http\Controllers\api\TalentManagement\Recruitment;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\TalentManagement\TalentJobApplication;
use App\Models\TalentManagement\TalentJobPosting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\api\Concerns\RequiresTalentAdmin;

/**
 * Ported 1:1 from hp_erp's `App\Http\Controllers\talent\talent_jobapplicationcontroller`.
 *
 * Auth/tenant: mirrors `OnboardingApiController` — `session()->get('sub_institute_id')`,
 * never request input. `created_by`/`updated_by` come from `session()->get('user_id')`,
 * never from request input (source took `updated_by` from `$request->user_id`,
 * which this project's actor-trust rule replaces with the session-resolved id).
 *
 * store()/update() accept both JSON and multipart bodies at the same URL —
 * `resume_path` is a real uploaded file on multipart requests, same as source.
 */
class JobApplicationController extends Controller
{
    use RequiresTalentAdmin;

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
     * GET job-applications
     * Ported from talent_jobapplicationcontroller@index (API branch only).
     */
    public function index(Request $request)
    {
        try {
            $subInstituteId = $this->subInstituteId();

            $talent = DB::table('talent_job_applications as a')
                ->select('*')
                ->where('a.sub_institute_id', $subInstituteId)
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
     * POST job-applications (multipart — resume_path is a required file)
     * Ported from talent_jobapplicationcontroller@store.
     */
    public function store(Request $request)
    {
        $subInstituteId = $this->subInstituteId();

        $validator = Validator::make($request->all(), [
            'job_id' => 'required|exists:talent_job_postings,id',
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'mobile' => 'required|string|max:20',
            'current_location' => 'nullable|string|max:255',
            'employment_type' => 'nullable|string|max:100',
            'experience' => 'nullable|string|max:255',
            'education' => 'nullable|string|max:255',
            'expected_salary' => 'nullable|numeric|min:0',
            'skills' => 'nullable|string',
            'certifications' => 'nullable|string',
            'applied_date' => 'nullable|date',
            'status' => 'required|in:Pending Review,Under Review,Shortlisted,Interview Scheduled,Rejected,Hired',
            'resume_path' => 'required|file|mimes:pdf,doc,docx|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 0,
                'message' => $validator->messages()->first(),
            ], 422);
        }

        try {
            $resumeFullUrl = null;

            if ($request->hasFile('resume_path')) {
                $file = $request->file('resume_path');
                $extension = $file->getClientOriginalExtension();
                $resumeFileName = 'resume_' . $subInstituteId . '_' . $request->first_name . '_' . $request->middle_name . '_' . $request->last_name . '.' . $extension;

                $filePath = 'public/hp_resume/' . $resumeFileName;

                Storage::disk('digitalocean')->putFileAs('public/hp_resume/', $file, $resumeFileName, 'public');

                $resumeFullUrl = Storage::disk('digitalocean')->url($filePath);
            }

            $objtalent = new TalentJobApplication([
                'job_id' => $request->job_id,
                'first_name' => $request->first_name,
                'middle_name' => $request->middle_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'mobile' => $request->mobile,
                'current_location' => $request->current_location,
                'employment_type' => $request->employment_type,
                'experience' => $request->experience,
                'education' => $request->education,
                'expected_salary' => $request->expected_salary,
                'skills' => $request->skills,
                'certifications' => $request->certifications,
                'resume_path' => $resumeFullUrl,
                'applied_date' => $request->applied_date,
                'status' => $request->status,
                'sub_institute_id' => $subInstituteId,
                'created_by' => $this->actorId(),
            ]);

            if ($objtalent->save()) {
                AuditLog::record([
                    'module' => 'talent_management',
                    'action' => 'job_application_created',
                    'entity_type' => 'job_application',
                    'entity_id' => $objtalent->id,
                    'new_values' => [
                        'job_id' => $objtalent->job_id,
                        'first_name' => $objtalent->first_name,
                        'last_name' => $objtalent->last_name,
                        'email' => $objtalent->email,
                        'status' => $objtalent->status,
                    ],
                ]);

                return response()->json([
                    'status' => 1,
                    'message' => 'Job application added successfully!',
                    'data' => $objtalent,
                ], 200);
            }

            return response()->json(['message' => 'Failed to save application'], 500);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 0,
                'error' => $e->getMessage(),
                'trace' => config('app.debug') ? $e->getTraceAsString() : null,
            ], 500);
        }
    }

    /**
     * GET job-applications/{id}
     * Ported from talent_jobapplicationcontroller@show (API branch only).
     */
    public function show(Request $request, $id)
    {
        try {
            $subInstituteId = $this->subInstituteId();

            $application = DB::table('talent_job_applications as a')
                ->select(
                    'a.id',
                    'a.job_id',
                    'a.first_name',
                    'a.middle_name',
                    'a.last_name',
                    'a.email',
                    'a.mobile',
                    'a.current_location',
                    'a.employment_type',
                    'a.experience',
                    'a.education',
                    'a.expected_salary',
                    'a.skills',
                    'a.certifications',
                    'a.resume_path',
                    'a.applied_date',
                    'a.status',
                    'a.created_by',
                    'a.updated_by'
                )
                ->where('a.sub_institute_id', $subInstituteId)
                ->where('a.id', $id)
                ->first();

            if (!$application) {
                return response()->json([
                    'message' => 'Job application not found.',
                ], 404);
            }

            return response()->json([
                'message' => 'Job application details fetched successfully!',
                'data' => $application,
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * PUT job-applications/{id} (JSON and multipart)
     * Ported from talent_jobapplicationcontroller@update.
     */
    public function update(Request $request, $id)
    {
        $subInstituteId = $this->subInstituteId();

        $validator = Validator::make($request->all(), [
            'job_id' => 'nullable|exists:talent_job_postings,id',
            'first_name' => 'nullable|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'mobile' => 'nullable|string|max:20',
            'current_location' => 'nullable|string|max:255',
            'expected_salary' => 'nullable|numeric|min:0',
            'resume_path' => 'nullable|file|mimes:pdf,doc,docx|max:5120',
            'applied_date' => 'nullable|date',
            'status' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 0,
                'message' => $validator->messages()->first(),
            ], 422);
        }

        try {
            $application = TalentJobApplication::find($id);

            if (!$application) {
                return response()->json(['message' => 'Application not found'], 404);
            }

            if (
                $request->filled('status')
                && $request->status === 'Shortlisted'
                && $application->hasReachedOfferOrHiredStage()
            ) {
                return response()->json(['message' => 'A candidate cannot be shortlisted after an offer has been sent or the candidate has been hired.'], 422);
            }

            if ($request->filled('job_id')) {
                $jobData = TalentJobPosting::find($request->job_id);
                if ($jobData) {
                    $application->job_id = $jobData->id;
                    $application->employment_type = $jobData->employment_type;
                    $application->experience = $jobData->experience;
                    $application->education = $jobData->education;
                    $application->skills = $jobData->skills;
                    $application->certifications = $jobData->certifications;
                }
            }

            $application->first_name = $request->first_name ?? $application->first_name;
            $application->middle_name = $request->middle_name ?? $application->middle_name;
            $application->last_name = $request->last_name ?? $application->last_name;
            $application->email = $request->email ?? $application->email;
            $application->mobile = $request->mobile ?? $application->mobile;
            $application->current_location = $request->current_location ?? $application->current_location;
            $application->expected_salary = $request->expected_salary ?? $application->expected_salary;
            $application->applied_date = $request->applied_date ?? $application->applied_date;
            $application->status = $request->status ?? $application->status;

            if ($request->hasFile('resume_path')) {
                $file = $request->file('resume_path');
                $extension = $file->getClientOriginalExtension();

                $resumeFileName = 'resume_' . $request->first_name . '_' . $request->middle_name . '_' . $request->last_name . '.' . $extension;

                Storage::disk('digitalocean')->putFileAs(
                    'public/hp_resume/',
                    $file,
                    $resumeFileName,
                    'public'
                );

                $resumeUrl = Storage::disk('digitalocean')->url('public/hp_resume/' . $resumeFileName);

                $application->resume_path = $resumeUrl;
            }

            $allowedStatuses = [
                'Pending Review',
                'Under Review',
                'Shortlisted',
                'Interview Scheduled',
                'Rejected',
                'Hired',
            ];

            if ($request->filled('status') && in_array($request->status, $allowedStatuses)) {
                $application->status = $request->status;
            }

            $application->updated_by = $this->actorId();
            $application->sub_institute_id = $subInstituteId;

            if ($application->save()) {
                AuditLog::record([
                    'module' => 'talent_management',
                    'action' => 'job_application_updated',
                    'entity_type' => 'job_application',
                    'entity_id' => $application->id,
                    'new_values' => [
                        'status' => $application->status,
                        'job_id' => $application->job_id,
                        'resume_path' => $application->resume_path,
                    ],
                ]);

                return response()->json([
                    'message' => 'Application updated successfully!',
                    'data' => $application,
                ], 200);
            }

            return response()->json(['message' => 'Update failed!'], 500);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST job-applications/{id}/status
     * Ported from talent_jobapplicationcontroller@updateStatus (API branch only).
     */
    public function updateStatus(Request $request, $id)
    {
        if ($response = $this->assertIsAdmin()) { return $response; }

        try {
            $subInstituteId = $this->subInstituteId();

            $validator = Validator::make($request->all(), [
                'status' => 'required|string|in:Pending Review,Under Review,Shortlisted,Interview Scheduled,Rejected,Hired,inactive',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status_code' => 0,
                    'message' => $validator->errors()->first(),
                ], 400);
            }

            $application = TalentJobApplication::where([
                'id' => $id,
                'sub_institute_id' => $subInstituteId,
            ])->first();

            if (!$application) {
                return response()->json(['message' => 'Job application not found'], 404);
            }

            if ($request->status === 'Shortlisted' && $application->hasReachedOfferOrHiredStage()) {
                return response()->json(['message' => 'A candidate cannot be shortlisted after an offer has been sent or the candidate has been hired.'], 422);
            }

            $application->status = $request->status;
            $application->updated_by = $this->actorId();

            if ($application->save()) {
                AuditLog::record([
                    'module' => 'talent_management',
                    'action' => 'job_application_status_updated',
                    'entity_type' => 'job_application',
                    'entity_id' => $application->id,
                    'new_values' => [
                        'status' => $application->status,
                    ],
                ]);

                return response()->json([
                    'message' => 'Application status updated successfully!',
                    'data' => [
                        'id' => $application->id,
                        'status' => $application->status,
                        'updated_by' => $application->updated_by,
                    ],
                ], 200);
            }

            return response()->json(['message' => 'Failed to update status'], 500);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET job-applications/candidate/{candidate_id}
     * Ported from talent_jobapplicationcontroller@getCandidateApplications (API branch only).
     */
    public function getCandidateApplications(Request $request, $candidate_id)
    {
        try {
            $subInstituteId = $this->subInstituteId();

            $applications = DB::table('talent_job_applications as a')
                ->join('talent_job_postings as j', 'a.job_id', '=', 'j.id')
                ->select(
                    'a.id as application_id',
                    'a.job_id',
                    'j.title as job_title',
                    'j.location as job_location',
                    'a.status',
                    'a.applied_date',
                    'a.expected_salary',
                    'a.resume_path',
                    'a.education',
                    'a.experience'
                )
                ->where('a.sub_institute_id', $subInstituteId)
                ->where('a.created_by', $candidate_id)
                ->orderBy('a.applied_date', 'desc')
                ->get();

            if ($applications->isEmpty()) {
                return response()->json([
                    'message' => 'No job applications found for this candidate.',
                    'data' => [],
                ], 200);
            }

            return response()->json([
                'message' => 'Candidate job applications fetched successfully!',
                'count' => $applications->count(),
                'data' => $applications,
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET job-applications/shortlisted
     * Ported from talent_jobapplicationcontroller@getShortlistedCandidates (API branch only).
     */
    public function getShortlistedCandidates(Request $request)
    {
        try {
            $subInstituteId = $this->subInstituteId();

            $applications = DB::table('talent_job_applications as a')
                ->select('*')
                ->where('a.sub_institute_id', $subInstituteId)
                ->where('a.status', 'Shortlisted')
                ->get();

            return response()->json([
                'message' => 'Shortlisted candidates fetched successfully!',
                'data' => $applications,
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // ------------------------------------------------------------------
    // Response helpers - same shape as OnboardingApiController's own.
    // Kept available per this project's controller convention; every
    // endpoint above keeps hp_erp's exact response shape instead, to avoid
    // breaking the existing frontend contract.
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
