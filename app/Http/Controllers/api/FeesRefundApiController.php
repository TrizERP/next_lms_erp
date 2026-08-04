<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\fees\fees_cancel\feesRefundController;
use App\Http\Controllers\fees\fees_collect\fees_collect_controller;
use App\Models\fees\bank_master\bankmasterModel;
use App\Models\fees\tblfeesConfigModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** JWT-authenticated API facade for the legacy Fees Refund workflow. */
class FeesRefundApiController extends Controller
{
    private function denyUnlessAllowed(string $action): ?JsonResponse
    {
        $profileName = strtolower((string) session('user_profile_name'));
        if (in_array($profileName, ['super admin', 'admin', 'school admin'], true)) return null;

        $menuId = DB::table('tblmenumaster')->where('status', 1)
            ->whereIn('link', ['fees_refund', 'fees/fees_refund'])->value('id');
        $profileId = session('user_profile_id');
        $userId = session('user_id');
        $tenantId = session('sub_institute_id');
        $rights = $menuId ? DB::table('tblindividual_rights')->where(['menu_id' => $menuId, 'profile_id' => $profileId, 'user_id' => $userId, 'sub_institute_id' => $tenantId])->first() : null;
        $rights = $rights ?: ($menuId ? DB::table('tblgroupwise_rights')->where(['menu_id' => $menuId, 'profile_id' => $profileId, 'sub_institute_id' => $tenantId])->first() : null);
        $column = $action === 'add' ? 'can_add' : 'can_view';
        if ((bool) ($rights->{$column} ?? false)) return null;
        return response()->json(['status_code' => 0, 'message' => 'You do not have permission to ' . $action . ' fees refunds.'], 403);
    }

    public function search(Request $request)
    {
        if ($denied = $this->denyUnlessAllowed('view')) return $denied;
        return (new feesRefundController())->showFees($request);
    }

    public function detail(Request $request, int $studentId): JsonResponse
    {
        if ($denied = $this->denyUnlessAllowed('view')) return $denied;
        $subInstituteId = (int) session('sub_institute_id');
        $syear = (int) session('syear');
        $breakoff = (new fees_collect_controller())->getBk($request, $studentId);
        if (empty($breakoff['stu_data']['student_id'])) return response()->json(['status_code' => 0, 'message' => 'Student not found.'], 404);
        $receipts = DB::table('fees_collect')->where(['student_id' => $studentId, 'sub_institute_id' => $subInstituteId, 'syear' => $syear, 'is_deleted' => 'N'])->get();
        $heads = [];
        foreach (($breakoff['final_fee_name'] ?? []) as $label => $column) $heads[(string) $column] = ['label' => $label, 'amount' => (float) $receipts->sum($column)];
        return response()->json(['status_code' => 1, 'message' => 'Success', 'student' => $breakoff['stu_data'], 'fee_heads' => $heads, 'banks' => bankmasterModel::all(), 'fees_config' => tblfeesConfigModel::where(['sub_institute_id' => $subInstituteId, 'syear' => $syear])->first()]);
    }

    public function save(Request $request)
    {
        if ($denied = $this->denyUnlessAllowed('add')) return $denied;
        return (new feesRefundController())->saveFeesRefund($request);
    }
}
