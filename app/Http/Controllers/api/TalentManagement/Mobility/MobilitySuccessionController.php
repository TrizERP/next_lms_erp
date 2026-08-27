<?php

namespace App\Http\Controllers\api\TalentManagement\Mobility;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\AuditLog;
use App\Models\TalentManagement\MobilitySuccessionPlan;
use App\Http\Controllers\api\TalentManagement\Mobility\Concerns\ResolvesMobilityContext;
use App\Http\Controllers\api\Concerns\RequiresTalentAdmin;

/**
 * Ported from G2G's `App\Http\Controllers\Api\Mobility\MobilitySuccessionController`.
 * Logic, validation rules and response envelope are unchanged from the source.
 */
class MobilitySuccessionController extends Controller
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
        $paging = $this->mobilityPaging($request, 10);

        $query = MobilitySuccessionPlan::with('criticalJobrole')
            ->where('sub_institute_id', $subInstituteId);

        if ($status = $request->input('status')) {
            if (strtolower($status) !== 'all') {
                $query->where('status', $status);
            }
        }

        $total = $query->count();
        $items = $query->orderBy('created_at', 'desc')
            ->offset(($paging['page'] - 1) * $paging['per_page'])
            ->limit($paging['per_page'])
            ->get();

        $userIds = $items->pluck('successor_user_id')->all();
        $directory = $this->mobilityDirectory($subInstituteId, $userIds);

        foreach ($items as $item) {
            $item->successor = $directory[$item->successor_user_id] ?? null;
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
            'critical_jobrole_id' => 'required|integer',
            'successor_user_id' => 'required|integer',
            'readiness' => 'required|string|in:Ready Now,Ready in 1-2 Years,Ready in 2+ Years',
            'emergency_successor' => 'boolean',
            'status' => 'required|string|in:Active,Inactive',
        ]);

        if ($validator->fails()) {
            return $this->mobilityError($validator->errors()->first(), 422);
        }

        // Fetch job role name
        $roleName = DB::table('s_user_jobrole')
            ->where('id', $request->input('critical_jobrole_id'))
            ->where('sub_institute_id', $subInstituteId)
            ->value('jobrole');

        if (!$roleName) {
            return $this->mobilityError('Critical jobrole not found', 404);
        }

        // Check if duplicate nomination exists
        $exists = MobilitySuccessionPlan::where('sub_institute_id', $subInstituteId)
            ->where('critical_jobrole_id', $request->input('critical_jobrole_id'))
            ->where('successor_user_id', $request->input('successor_user_id'))
            ->exists();

        if ($exists) {
            return $this->mobilityError('This successor has already been nominated for this role', 400);
        }

        $planData = array_merge($validator->validated(), [
            'sub_institute_id' => $subInstituteId,
            'critical_jobrole_name' => $roleName,
            'created_by' => $actorId,
        ]);

        $plan = MobilitySuccessionPlan::create($planData);

        AuditLog::record([
            'module' => 'talent_management',
            'action' => 'mobility_succession_plan.created',
            'entity_type' => 'mobility_succession_plan',
            'entity_id' => $plan->id,
            'new_values' => $planData,
        ]);

        return $this->mobilityResponse($plan, 'Succession plan created successfully', 201);
    }

    public function update(Request $request, $id)
    {
        if ($response = $this->assertIsAdmin()) { return $response; }

        $context = $this->mobilityContext($request);
        if ($context instanceof \Illuminate\Http\JsonResponse) {
            return $context;
        }

        $plan = MobilitySuccessionPlan::where('sub_institute_id', $context['sub_institute_id'])
            ->find($id);

        if (!$plan) {
            return $this->mobilityError('Succession plan not found', 404);
        }

        $validator = Validator::make($request->all(), [
            'readiness' => 'sometimes|required|string|in:Ready Now,Ready in 1-2 Years,Ready in 2+ Years',
            'emergency_successor' => 'sometimes|boolean',
            'status' => 'sometimes|required|string|in:Active,Inactive',
        ]);

        if ($validator->fails()) {
            return $this->mobilityError($validator->errors()->first(), 422);
        }

        $planUpdate = array_merge($validator->validated(), [
            'updated_by' => $context['user_id'],
        ]);

        $plan->update($planUpdate);

        AuditLog::record([
            'module' => 'talent_management',
            'action' => 'mobility_succession_plan.updated',
            'entity_type' => 'mobility_succession_plan',
            'entity_id' => $plan->id,
            'new_values' => $planUpdate,
        ]);

        return $this->mobilityResponse($plan, 'Succession plan updated successfully');
    }

    public function destroy(Request $request, $id)
    {
        if ($response = $this->assertIsAdmin()) { return $response; }

        $context = $this->mobilityContext($request);
        if ($context instanceof \Illuminate\Http\JsonResponse) {
            return $context;
        }

        $plan = MobilitySuccessionPlan::where('sub_institute_id', $context['sub_institute_id'])
            ->find($id);

        if (!$plan) {
            return $this->mobilityError('Succession plan not found', 404);
        }

        $plan->update(['deleted_by' => $context['user_id']]);
        $plan->delete();

        AuditLog::record([
            'module' => 'talent_management',
            'action' => 'mobility_succession_plan.deleted',
            'entity_type' => 'mobility_succession_plan',
            'entity_id' => $plan->id,
            'new_values' => ['deleted_by' => $context['user_id']],
        ]);

        return $this->mobilityResponse(null, 'Succession plan deleted successfully');
    }
}
