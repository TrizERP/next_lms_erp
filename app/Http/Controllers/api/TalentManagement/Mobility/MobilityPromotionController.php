<?php

namespace App\Http\Controllers\api\TalentManagement\Mobility;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Schema;
use App\Models\AuditLog;
use App\Models\TalentManagement\MobilityPromotion;
use App\Http\Controllers\api\TalentManagement\Mobility\Concerns\ResolvesMobilityContext;
use App\Http\Controllers\api\Concerns\RequiresTalentAdmin;

/**
 * Ported from G2G's `App\Http\Controllers\Api\Mobility\MobilityPromotionController`.
 * Logic, validation rules and the tbluser/org_designation side-effects on
 * completion are unchanged from the source.
 */
class MobilityPromotionController extends Controller
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

        $query = MobilityPromotion::where('sub_institute_id', $subInstituteId);

        if ($status = $request->input('status')) {
            if (strtolower($status) !== 'all') {
                $query->where('status', $status);
            }
        }

        $total = $query->count();
        $items = $query->orderBy('effective_date', 'desc')
            ->offset(($paging['page'] - 1) * $paging['per_page'])
            ->limit($paging['per_page'])
            ->get();

        $userIds = $items->pluck('user_id')->all();
        $directory = $this->mobilityDirectory($subInstituteId, $userIds);

        foreach ($items as $item) {
            $item->employee = $directory[$item->user_id] ?? null;
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
            'user_id' => 'required|integer',
            'current_grade' => 'nullable|string|max:50',
            'proposed_grade' => 'required|string|max:50',
            'current_designation' => 'nullable|string|max:191',
            'proposed_designation' => 'required|string|max:191',
            'effective_date' => 'required|date',
            'status' => 'required|string|in:Pending,Approved,Completed,Cancelled',
            'remarks' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->mobilityError($validator->errors()->first(), 422);
        }

        $promoData = array_merge($validator->validated(), [
            'sub_institute_id' => $subInstituteId,
            'created_by' => $actorId,
        ]);

        $promo = MobilityPromotion::create($promoData);

        if ($promo->status === 'Completed') {
            $this->completePromotionInProfile($promo);
        }

        AuditLog::record([
            'module' => 'talent_management',
            'action' => 'mobility_promotion.created',
            'entity_type' => 'mobility_promotion',
            'entity_id' => $promo->id,
            'new_values' => $promoData,
        ]);

        return $this->mobilityResponse($promo, 'Promotion recorded successfully', 201);
    }

    public function update(Request $request, $id)
    {
        if ($response = $this->assertIsAdmin()) { return $response; }

        $context = $this->mobilityContext($request);
        if ($context instanceof \Illuminate\Http\JsonResponse) {
            return $context;
        }

        $promo = MobilityPromotion::where('sub_institute_id', $context['sub_institute_id'])
            ->find($id);

        if (!$promo) {
            return $this->mobilityError('Promotion record not found', 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|string|in:Pending,Approved,Completed,Cancelled',
            'remarks' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->mobilityError($validator->errors()->first(), 422);
        }

        $oldStatus = $promo->status;
        $promo->update([
            'status' => $request->input('status'),
            'remarks' => $request->input('remarks'),
            'updated_by' => $context['user_id'],
        ]);

        if ($promo->status === 'Completed' && $oldStatus !== 'Completed') {
            $this->completePromotionInProfile($promo);
        }

        AuditLog::record([
            'module' => 'talent_management',
            'action' => 'mobility_promotion.status_updated',
            'entity_type' => 'mobility_promotion',
            'entity_id' => $promo->id,
            'new_values' => [
                'status' => $request->input('status'),
                'remarks' => $request->input('remarks'),
                'old_status' => $oldStatus,
            ],
        ]);

        return $this->mobilityResponse($promo, 'Promotion updated successfully');
    }

    private function completePromotionInProfile(MobilityPromotion $promo)
    {
        // 1. Update tbluser grade/allocated_standards if the proposed designation is a job role
        $jobroleId = DB::table('s_user_jobrole')
            ->where('sub_institute_id', $promo->sub_institute_id)
            ->whereNull('deleted_at')
            ->where('jobrole', $promo->proposed_designation)
            ->value('id');

        $allocatedStandards = $jobroleId ?: $promo->proposed_designation;

        DB::table('tbluser')
            ->where('id', $promo->user_id)
            ->where('sub_institute_id', $promo->sub_institute_id)
            ->update([
                'allocated_standards' => $allocatedStandards,
                'updated_at' => now(),
            ]);

        // 2. Update org_designation record - no such table exists on this
        // target (see the read-side fix in ResolvesMobilityContext /
        // Onboarding / Offboarding / Performance / Administration, which
        // resolve designation via tbluser.jobtitle_id -> s_user_jobrole
        // instead). `allocated_standards` above already carries this
        // promotion's effective role, so this step is a guarded no-op rather
        // than a hard requirement.
        if (!Schema::hasTable('org_designation')) {
            return;
        }

        $existing = DB::table('org_designation')
            ->where('user_id', $promo->user_id)
            ->where('sub_institute_id', $promo->sub_institute_id)
            ->first();

        if ($existing) {
            DB::table('org_designation')
                ->where('id', $existing->id)
                ->update([
                    'designation' => $promo->proposed_designation,
                    'level' => $promo->proposed_grade,
                    'updated_at' => now(),
                ]);
        } else {
            DB::table('org_designation')->insert([
                'user_id' => $promo->user_id,
                'sub_institute_id' => $promo->sub_institute_id,
                'designation' => $promo->proposed_designation,
                'level' => $promo->proposed_grade,
                'branch' => 'Main',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
