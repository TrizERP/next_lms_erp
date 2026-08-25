<?php

namespace App\Http\Controllers\api\TalentManagement\Mobility;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\AuditLog;
use App\Models\TalentManagement\MobilityJob;
use App\Http\Controllers\api\TalentManagement\Mobility\Concerns\ResolvesMobilityContext;
use App\Http\Controllers\api\Concerns\RequiresTalentAdmin;

/**
 * Ported from G2G's `App\Http\Controllers\Api\Mobility\MobilityJobController`.
 * Logic, validation rules and response envelope are unchanged from the
 * source — only the namespace, model namespace, and session-based context
 * resolution differ.
 */
class MobilityJobController extends Controller
{
    use ResolvesMobilityContext;
    use RequiresTalentAdmin;

    public function index(Request $request)
    {
        $context = $this->mobilityContext($request);
        if ($context instanceof \Illuminate\Http\JsonResponse) {
            return $context;
        }

        $subInstituteId = $context['sub_institute_id'];
        $paging = $this->mobilityPaging($request, 10); // Reuses max 200 clamp
        $sort = $this->mobilitySort($request, ['title', 'posted_on', 'deadline', 'vacancies'], 'posted_on', 'desc');

        $query = MobilityJob::where('sub_institute_id', $subInstituteId);

        // Search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('job_id', 'like', "%{$search}%")
                  ->orWhere('department', 'like', "%{$search}%")
                  ->orWhere('location', 'like', "%{$search}%");
            });
        }

        // Filters
        if ($dept = $request->input('department')) {
            if (strtolower($dept) !== 'all') {
                $query->where('department', $dept);
            }
        }
        if ($loc = $request->input('location')) {
            if (strtolower($loc) !== 'all') {
                $query->where('location', $loc);
            }
        }
        if ($grade = $request->input('grade')) {
            if (strtolower($grade) !== 'all') {
                $query->where('grade', $grade);
            }
        }
        if ($jobType = $request->input('job_type')) {
            if (strtolower($jobType) !== 'all') {
                $query->where('job_type', $jobType);
            }
        }
        if ($status = $request->input('status')) {
            if (strtolower($status) !== 'all') {
                $query->where('status', $status);
            }
        }

        $total = $query->count();
        $items = $query->orderBy($sort[0], $sort[1])
            ->offset(($paging['page'] - 1) * $paging['per_page'])
            ->limit($paging['per_page'])
            ->get();

        // Top up with application counters
        foreach ($items as $item) {
            $item->applications_count = DB::table('s_mobility_applications')
                ->where('job_posting_id', $item->id)
                ->whereNull('deleted_at')
                ->count();
        }

        return $this->mobilityResponse($items, 'Success', 200, [
            'total' => $total,
            'page' => $paging['page'],
            'per_page' => $paging['per_page'],
        ]);
    }

    public function store(Request $request)
    {
        if ($response = $this->assertIsAdmin()) { return $response; }

        $context = $this->mobilityContext($request);
        if ($context instanceof \Illuminate\Http\JsonResponse) {
            return $context;
        }

        $subInstituteId = $context['sub_institute_id'];
        $actorId = $context['user_id'];

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:191',
            'department_id' => 'nullable|integer',
            'location' => 'required|string|max:191',
            'grade' => 'required|string|max:50',
            'job_type' => 'required|string|in:Permanent,Contract,Temporary',
            'posted_on' => 'nullable|date',
            'deadline' => 'nullable|date',
            'hiring_manager_id' => 'nullable|integer',
            'current_incumbent' => 'nullable|string|max:191',
            'vacancies' => 'required|integer|min:1',
            'relocation_required' => 'required|string|in:Yes,No,Case-by-case',
            'description' => 'nullable|string',
            'status' => 'required|string|in:Open,In Review,Closed',
        ]);

        if ($validator->fails()) {
            return $this->mobilityError($validator->errors()->first(), 422);
        }

        // Auto-generate a job_id (e.g. INT-2026-0001)
        $year = now()->year;
        $prefix = "INT-{$year}-";
        $last = MobilityJob::where('sub_institute_id', $subInstituteId)
            ->where('job_id', 'like', $prefix . '%')
            ->orderByDesc('job_id')
            ->value('job_id');
        $sequence = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;
        $jobIdCode = $prefix . str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);

        // Fetch department name if department_id is passed
        $deptName = null;
        if ($deptId = $request->input('department_id')) {
            $deptName = DB::table('hrms_departments')->where('id', $deptId)->value('department');
        }

        // Fetch hiring manager name
        $hmName = null;
        if ($hmId = $request->input('hiring_manager_id')) {
            $hm = DB::table('tbluser')->where('id', $hmId)->first(['first_name', 'last_name']);
            if ($hm) {
                $hmName = trim(($hm->first_name ?? '') . ' ' . ($hm->last_name ?? ''));
            }
        }

        $postedOn = $request->input('posted_on') ?: now()->toDateString();

        $jobData = array_merge($validator->validated(), [
            'sub_institute_id' => $subInstituteId,
            'job_id' => $jobIdCode,
            'department' => $deptName,
            'hiring_manager_name' => $hmName,
            'posted_on' => $postedOn,
            'created_by' => $actorId,
        ]);

        $job = MobilityJob::create($jobData);

        AuditLog::record([
            'module' => 'talent_management',
            'action' => 'mobility_job.created',
            'entity_type' => 'mobility_job',
            'entity_id' => $job->id,
            'new_values' => $jobData,
        ]);

        return $this->mobilityResponse($job, 'Job posting created successfully', 201);
    }

    public function show(Request $request, $id)
    {
        $context = $this->mobilityContext($request);
        if ($context instanceof \Illuminate\Http\JsonResponse) {
            return $context;
        }

        $job = MobilityJob::where('sub_institute_id', $context['sub_institute_id'])
            ->find($id);

        if (!$job) {
            return $this->mobilityError('Job posting not found', 404);
        }

        $job->applications_count = DB::table('s_mobility_applications')
            ->where('job_posting_id', $job->id)
            ->whereNull('deleted_at')
            ->count();

        return $this->mobilityResponse($job);
    }

    public function update(Request $request, $id)
    {
        if ($response = $this->assertIsAdmin()) { return $response; }

        $context = $this->mobilityContext($request);
        if ($context instanceof \Illuminate\Http\JsonResponse) {
            return $context;
        }

        $job = MobilityJob::where('sub_institute_id', $context['sub_institute_id'])
            ->find($id);

        if (!$job) {
            return $this->mobilityError('Job posting not found', 404);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:191',
            'department_id' => 'nullable|integer',
            'location' => 'sometimes|required|string|max:191',
            'grade' => 'sometimes|required|string|max:50',
            'job_type' => 'sometimes|required|string|in:Permanent,Contract,Temporary',
            'posted_on' => 'sometimes|required|date',
            'deadline' => 'nullable|date|after_or_equal:posted_on',
            'hiring_manager_id' => 'nullable|integer',
            'current_incumbent' => 'nullable|string|max:191',
            'vacancies' => 'sometimes|required|integer|min:1',
            'relocation_required' => 'sometimes|required|string|in:Yes,No,Case-by-case',
            'description' => 'nullable|string',
            'status' => 'sometimes|required|string|in:Open,In Review,Closed',
        ]);

        if ($validator->fails()) {
            return $this->mobilityError($validator->errors()->first(), 422);
        }

        $data = $validator->validated();

        // Update department name if changed
        if ($request->has('department_id')) {
            $deptId = $request->input('department_id');
            $data['department'] = $deptId ? DB::table('hrms_departments')->where('id', $deptId)->value('department') : null;
        }

        // Update hiring manager name if changed
        if ($request->has('hiring_manager_id')) {
            $hmId = $request->input('hiring_manager_id');
            if ($hmId) {
                $hm = DB::table('tbluser')->where('id', $hmId)->first(['first_name', 'last_name']);
                $data['hiring_manager_name'] = $hm ? trim(($hm->first_name ?? '') . ' ' . ($hm->last_name ?? '')) : null;
            } else {
                $data['hiring_manager_name'] = null;
            }
        }

        $updateData = array_merge($data, [
            'updated_by' => $context['user_id'],
        ]);

        $job->update($updateData);

        AuditLog::record([
            'module' => 'talent_management',
            'action' => 'mobility_job.updated',
            'entity_type' => 'mobility_job',
            'entity_id' => $job->id,
            'new_values' => $updateData,
        ]);

        return $this->mobilityResponse($job, 'Job posting updated successfully');
    }

    public function destroy(Request $request, $id)
    {
        if ($response = $this->assertIsAdmin()) { return $response; }

        $context = $this->mobilityContext($request);
        if ($context instanceof \Illuminate\Http\JsonResponse) {
            return $context;
        }

        $job = MobilityJob::where('sub_institute_id', $context['sub_institute_id'])
            ->find($id);

        if (!$job) {
            return $this->mobilityError('Job posting not found', 404);
        }

        $job->update(['deleted_by' => $context['user_id']]);
        $job->delete();

        AuditLog::record([
            'module' => 'talent_management',
            'action' => 'mobility_job.deleted',
            'entity_type' => 'mobility_job',
            'entity_id' => $job->id,
            'new_values' => ['deleted_by' => $context['user_id']],
        ]);

        return $this->mobilityResponse(null, 'Job posting deleted successfully');
    }
}
