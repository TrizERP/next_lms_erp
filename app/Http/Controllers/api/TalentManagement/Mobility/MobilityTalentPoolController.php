<?php

namespace App\Http\Controllers\api\TalentManagement\Mobility;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\AuditLog;
use App\Models\TalentManagement\MobilityTalentPool;
use App\Models\TalentManagement\MobilityTalentPoolMember;
use App\Http\Controllers\api\TalentManagement\Mobility\Concerns\ResolvesMobilityContext;
use App\Http\Controllers\api\Concerns\RequiresTalentAdmin;

/**
 * Ported from G2G's `App\Http\Controllers\Api\Mobility\MobilityTalentPoolController`.
 * Logic, validation rules and response envelope are unchanged from the source.
 */
class MobilityTalentPoolController extends Controller
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

        $query = MobilityTalentPool::where('sub_institute_id', $subInstituteId);

        if ($status = $request->input('status')) {
            if (strtolower($status) !== 'all') {
                $query->where('status', $status);
            }
        }

        $total = $query->count();
        $items = $query->orderBy('name', 'asc')
            ->offset(($paging['page'] - 1) * $paging['per_page'])
            ->limit($paging['per_page'])
            ->get();

        // Load member counts
        foreach ($items as $item) {
            $item->members_count = DB::table('s_mobility_talent_pool_members')
                ->where('pool_id', $item->id)
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
            'name' => 'required|string|max:191',
            'description' => 'nullable|string',
            'status' => 'required|string|in:Active,Inactive',
        ]);

        if ($validator->fails()) {
            return $this->mobilityError($validator->errors()->first(), 422);
        }

        $pool = MobilityTalentPool::create([
            'sub_institute_id' => $subInstituteId,
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'status' => $request->input('status'),
            'created_by' => $actorId,
        ]);

        AuditLog::record([
            'module' => 'talent_management',
            'action' => 'mobility_talent_pool.created',
            'entity_type' => 'mobility_talent_pool',
            'entity_id' => $pool->id,
            'new_values' => [
                'name' => $request->input('name'),
                'status' => $request->input('status'),
            ],
        ]);

        return $this->mobilityResponse($pool, 'Talent pool created successfully', 201);
    }

    public function members(Request $request, $id)
    {
        $context = $this->mobilityContext($request);
        if ($context instanceof \Illuminate\Http\JsonResponse) {
            return $context;
        }

        $subInstituteId = $context['sub_institute_id'];
        $pool = MobilityTalentPool::where('sub_institute_id', $subInstituteId)->find($id);

        if (!$pool) {
            return $this->mobilityError('Talent pool not found', 404);
        }

        $members = MobilityTalentPoolMember::where('pool_id', $id)->get();
        $userIds = $members->pluck('user_id')->all();
        $directory = $this->mobilityDirectory($subInstituteId, $userIds);

        foreach ($members as $member) {
            $member->user_details = $directory[$member->user_id] ?? null;
        }

        return $this->mobilityResponse($members);
    }

    public function addMember(Request $request, $id)
    {
        if ($response = $this->assertIsAdmin()) { return $response; }

        $context = $this->mobilityContext($request);
        if ($context instanceof \Illuminate\Http\JsonResponse) {
            return $context;
        }

        $subInstituteId = $context['sub_institute_id'];
        $pool = MobilityTalentPool::where('sub_institute_id', $subInstituteId)->find($id);

        if (!$pool) {
            return $this->mobilityError('Talent pool not found', 404);
        }

        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return $this->mobilityError($validator->errors()->first(), 422);
        }

        $userId = $request->input('user_id');

        // Check duplicate member
        $exists = MobilityTalentPoolMember::where('pool_id', $id)
            ->where('user_id', $userId)
            ->exists();

        if ($exists) {
            return $this->mobilityError('Employee is already a member of this talent pool', 400);
        }

        $member = MobilityTalentPoolMember::create([
            'pool_id' => $id,
            'user_id' => $userId,
            'added_on' => now()->toDateString(),
            'created_by' => $context['user_id'],
        ]);

        AuditLog::record([
            'module' => 'talent_management',
            'action' => 'mobility_talent_pool.member_added',
            'entity_type' => 'mobility_talent_pool_member',
            'entity_id' => $member->id,
            'new_values' => [
                'pool_id' => $id,
                'user_id' => $userId,
            ],
        ]);

        return $this->mobilityResponse($member, 'Member added to pool successfully', 201);
    }

    public function removeMember(Request $request, $id, $userId)
    {
        if ($response = $this->assertIsAdmin()) { return $response; }

        $context = $this->mobilityContext($request);
        if ($context instanceof \Illuminate\Http\JsonResponse) {
            return $context;
        }

        $subInstituteId = $context['sub_institute_id'];
        $pool = MobilityTalentPool::where('sub_institute_id', $subInstituteId)->find($id);

        if (!$pool) {
            return $this->mobilityError('Talent pool not found', 404);
        }

        $member = MobilityTalentPoolMember::where('pool_id', $id)
            ->where('user_id', $userId)
            ->first();

        if (!$member) {
            return $this->mobilityError('Member not found in this pool', 404);
        }

        $memberId = $member->id;
        $member->delete();

        AuditLog::record([
            'module' => 'talent_management',
            'action' => 'mobility_talent_pool.member_removed',
            'entity_type' => 'mobility_talent_pool_member',
            'entity_id' => $memberId,
            'new_values' => [
                'pool_id' => $id,
                'user_id' => $userId,
            ],
        ]);

        return $this->mobilityResponse(null, 'Member removed from pool successfully');
    }
}
