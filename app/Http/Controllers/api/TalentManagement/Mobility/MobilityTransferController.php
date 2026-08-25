<?php

namespace App\Http\Controllers\api\TalentManagement\Mobility;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\AuditLog;
use App\Models\TalentManagement\MobilityTransfer;
use App\Http\Controllers\api\TalentManagement\Mobility\Concerns\ResolvesMobilityContext;
use App\Http\Controllers\api\Concerns\RequiresTalentAdmin;

/**
 * Ported from G2G's `App\Http\Controllers\Api\Mobility\MobilityTransferController`.
 * Logic, validation rules and the tbluser/org side-effects on completion are
 * unchanged from the source.
 */
class MobilityTransferController extends Controller
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

        $query = MobilityTransfer::where('sub_institute_id', $subInstituteId);

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
            'from_department_id' => 'nullable|integer',
            'to_department_id' => 'required|integer',
            'from_jobrole' => 'nullable|string|max:191',
            'to_jobrole' => 'required|string|max:191',
            'effective_date' => 'required|date',
            'status' => 'required|string|in:Pending,Approved,Completed,Cancelled',
            'remarks' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->mobilityError($validator->errors()->first(), 422);
        }

        // Fetch department names
        $fromDept = $request->input('from_department_id')
            ? DB::table('hrms_departments')->where('id', $request->input('from_department_id'))->value('department')
            : null;
        $toDept = DB::table('hrms_departments')->where('id', $request->input('to_department_id'))->value('department');

        $transferData = array_merge($validator->validated(), [
            'sub_institute_id' => $subInstituteId,
            'from_department' => $fromDept,
            'to_department' => $toDept,
            'created_by' => $actorId,
        ]);

        $transfer = MobilityTransfer::create($transferData);

        if ($transfer->status === 'Completed') {
            $this->completeTransferInProfile($transfer);
        }

        AuditLog::record([
            'module' => 'talent_management',
            'action' => 'mobility_transfer.created',
            'entity_type' => 'mobility_transfer',
            'entity_id' => $transfer->id,
            'new_values' => $transferData,
        ]);

        return $this->mobilityResponse($transfer, 'Transfer recorded successfully', 201);
    }

    public function update(Request $request, $id)
    {
        if ($response = $this->assertIsAdmin()) { return $response; }

        $context = $this->mobilityContext($request);
        if ($context instanceof \Illuminate\Http\JsonResponse) {
            return $context;
        }

        $transfer = MobilityTransfer::where('sub_institute_id', $context['sub_institute_id'])
            ->find($id);

        if (!$transfer) {
            return $this->mobilityError('Transfer record not found', 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|string|in:Pending,Approved,Completed,Cancelled',
            'remarks' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->mobilityError($validator->errors()->first(), 422);
        }

        $oldStatus = $transfer->status;
        $transfer->update([
            'status' => $request->input('status'),
            'remarks' => $request->input('remarks'),
            'updated_by' => $context['user_id'],
        ]);

        if ($transfer->status === 'Completed' && $oldStatus !== 'Completed') {
            $this->completeTransferInProfile($transfer);
        }

        AuditLog::record([
            'module' => 'talent_management',
            'action' => 'mobility_transfer.status_updated',
            'entity_type' => 'mobility_transfer',
            'entity_id' => $transfer->id,
            'new_values' => [
                'status' => $request->input('status'),
                'remarks' => $request->input('remarks'),
                'old_status' => $oldStatus,
            ],
        ]);

        return $this->mobilityResponse($transfer, 'Transfer updated successfully');
    }

    private function completeTransferInProfile(MobilityTransfer $transfer)
    {
        // Auto-resolve jobrole_id for target jobrole in s_user_jobrole if it exists
        $jobroleId = DB::table('s_user_jobrole')
            ->where('sub_institute_id', $transfer->sub_institute_id)
            ->whereNull('deleted_at')
            ->where('jobrole', $transfer->to_jobrole)
            ->value('id');

        $allocatedStandards = $jobroleId ?: $transfer->to_jobrole;

        DB::table('tbluser')
            ->where('id', $transfer->user_id)
            ->where('sub_institute_id', $transfer->sub_institute_id)
            ->update([
                'department_id' => $transfer->to_department_id,
                'allocated_standards' => $allocatedStandards,
                'updated_at' => now(),
            ]);
    }
}
