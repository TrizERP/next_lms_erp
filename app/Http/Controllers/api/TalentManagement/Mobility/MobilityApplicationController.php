<?php

namespace App\Http\Controllers\api\TalentManagement\Mobility;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\TalentManagement\MobilityApplication;
use App\Models\TalentManagement\MobilityJob;
use App\Http\Controllers\api\TalentManagement\Mobility\Concerns\ResolvesMobilityContext;

/**
 * Ported from G2G's `App\Http\Controllers\Api\Mobility\MobilityApplicationController`.
 * Logic, validation rules and response envelope are unchanged from the source.
 */
class MobilityApplicationController extends Controller
{
    use ResolvesMobilityContext;

    public function index(Request $request)
    {
        $context = $this->mobilityContext($request);
        if ($context instanceof \Illuminate\Http\JsonResponse) {
            return $context;
        }

        $subInstituteId = $context['sub_institute_id'];
        $paging = $this->mobilityPaging($request, 10);

        $query = MobilityApplication::with('job')
            ->where('sub_institute_id', $subInstituteId);

        if ($jobId = $request->input('job_posting_id')) {
            $query->where('job_posting_id', $jobId);
        }

        if ($status = $request->input('status')) {
            if (strtolower($status) !== 'all') {
                $query->where('status', $status);
            }
        }

        $total = $query->count();
        $items = $query->orderBy('applied_on', 'desc')
            ->offset(($paging['page'] - 1) * $paging['per_page'])
            ->limit($paging['per_page'])
            ->get();

        // Load applicant details from directory
        $userIds = $items->pluck('user_id')->all();
        $directory = $this->mobilityDirectory($subInstituteId, $userIds);

        foreach ($items as $item) {
            $item->applicant = $directory[$item->user_id] ?? null;
        }

        return $this->mobilityResponse($items, 'Success', 200, [
            'total' => $total,
            'page' => $paging['page'],
            'per_page' => $paging['per_page'],
        ]);
    }

    public function store(Request $request)
    {
        $context = $this->mobilityContext($request);
        if ($context instanceof \Illuminate\Http\JsonResponse) {
            return $context;
        }

        $subInstituteId = $context['sub_institute_id'];
        $actorId = $context['user_id'];

        $validator = Validator::make($request->all(), [
            'job_posting_id' => 'required|integer',
            'user_id' => 'nullable|integer',
            'remarks' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->mobilityError($validator->errors()->first(), 422);
        }

        $jobId = $request->input('job_posting_id');
        $job = MobilityJob::where('sub_institute_id', $subInstituteId)->find($jobId);
        if (!$job) {
            return $this->mobilityError('Job posting not found', 404);
        }

        $userId = $request->input('user_id') ?: $actorId;
        if (!$userId) {
            return $this->mobilityError('User ID is required', 400);
        }

        // Check if already applied
        $exists = MobilityApplication::where('sub_institute_id', $subInstituteId)
            ->where('job_posting_id', $jobId)
            ->where('user_id', $userId)
            ->exists();

        if ($exists) {
            return $this->mobilityError('You have already applied for this job', 400);
        }

        $app = MobilityApplication::create([
            'sub_institute_id' => $subInstituteId,
            'job_posting_id' => $jobId,
            'user_id' => $userId,
            'applied_on' => now()->toDateString(),
            'status' => 'Applied',
            'remarks' => $request->input('remarks'),
            'created_by' => $actorId,
        ]);

        return $this->mobilityResponse($app, 'Application submitted successfully', 201);
    }

    public function update(Request $request, $id)
    {
        $context = $this->mobilityContext($request);
        if ($context instanceof \Illuminate\Http\JsonResponse) {
            return $context;
        }

        $app = MobilityApplication::where('sub_institute_id', $context['sub_institute_id'])
            ->find($id);

        if (!$app) {
            return $this->mobilityError('Application not found', 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|string|in:Applied,Screening,Interviewing,Offered,Rejected,Closed',
            'remarks' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->mobilityError($validator->errors()->first(), 422);
        }

        $app->update([
            'status' => $request->input('status'),
            'remarks' => $request->input('remarks'),
            'updated_by' => $context['user_id'],
        ]);

        return $this->mobilityResponse($app, 'Application status updated successfully');
    }
}
